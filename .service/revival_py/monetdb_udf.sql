-- requires libIncCD.so
-- can be downloaded+built from:
-- git clone https://github.com/eXascaleInfolab/CD_tool.git
-- cd CD_tool
-- mkdir cmake-build-debug
-- make library

-- then either:
-- sudo cp cmake-build-debug/libIncCD.so /usr/local/lib
    -- or
-- replace the path at the start of the function with a relative path
-- #define CENTROID_LIBRARY_PATH "/path/to/repo/CD_tool/cmake-build-debug/libIncCD.so";

--usage:
-- SELECT SELECT series_id, sys.epoch(datetime) as timeval, value_recovered FROM centroid_recovery_revival((
--    SUBQUERY_SELECTING_VALUES_WITH_NULLS
-- );

SET SCHEMA sys;
DROP FUNCTION IF EXISTS centroid_predict_revival;
CREATE FUNCTION centroid_predict_revival(
        sid_in INTEGER, dt_in TIMESTAMP, tsval FLOAT
    )
RETURNS TABLE(
        series_id INTEGER, datetime TIMESTAMP, value_recovered FLOAT
    )
LANGUAGE PYTHON
{
    ### load all dependencies
    import sys;
    import numpy as np;
    import importlib;
    from statsmodels.tsa.ar_model import AR;

    wd = '/var/monetdb5/revival_py/';
    sys.path.append(wd);
    from native_centroid import centroid as cdnat;
    from native_centroid import predictor as pred;

    ### predict a matrix

    m = len(set(sid_in));
    n = len(sid_in)/m;
    use_normalization = True;

    mat = np.array([tsval]).reshape((m, n)).T;

    # normalize
    mean = np.array([0.0] * m);
    stddev = np.array([0.0] * m);
    nonan = np.array([0] * m);
    
    if use_normalization:
        for j in range(0, m):
            for i in range(0, n):
                mval = mat[i, j];
                if not np.isnan(mval):
                    mean[j] += mval;
                    stddev[j] += mval * mval;
                    nonan[j] += 1;
                #end if
            #end for
            stddev[j] -= (mean[j] * mean[j] / nonan[j]);
            stddev[j] = np.sqrt(stddev[j] / (nonan[j]-1));
            mean[j] /= nonan[j];
            for i in range(0, n):
                mat[i, j] = (mat[i, j] - mean[j]) / stddev[j]; #will fire on nans too, but (double.nan - double) / double == double.nan;
            #end for
        #end for
    
    # predict
    mat = pred.predict_CD_AR_PY_trendL(mat=mat, _param_ic = 't-stat', _param_trans = False, _param_linlag = 20);

    # denormalize
    if use_normalization:
        for j in range(0, m):
            for i in range(0, n):
                mat[i, j] = (mat[i,j] * stddev[j]) + mean[j];
            #end for
        #end for
    
    ### return results
    return [sid_in, dt_in, mat.T.reshape(n * m)];
};

-- example

SET SCHEMA data;

-- selector for a few time series, they already contain nulls in this table
SELECT series_id, sys.epoch(datetime) as timeval, value
FROM hourly
WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
    AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
ORDER BY datetime;

-- same selector, but with a pass-through of centroid_recovery_revival/5 to recover all nulls
SELECT series_id, sys.epoch(datetime) as timeval, value_recovered FROM sys.centroid_predict_revival((
    SELECT series_id, datetime, value
    FROM hourly
    WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
        AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
)) ORDER BY datetime;
