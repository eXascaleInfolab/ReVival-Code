import numpy as np;
from numpy import linalg as LA;
from statsmodels.tsa.ar_model import AR;
from statsmodels.tsa.arima_model import ARIMA
from statsmodels.tsa.seasonal import seasonal_decompose;
import statsmodels.api as sm;
import scipy.stats as scistat;

from native_centroid import centroid as cdnat;


def LinearRegressionPrediction(mat, minidx, linlag):
    # load dataset
    n = len(mat);
    m = len(mat[0]);

    #calculate "latest range"
    startidx = max(minidx - linlag, 0); #???

    slopes = np.array([0.0] * m);
    slopes2 = np.array([0.0] * m);
    intercepts = np.array([0.0] * m);
    
    for i in range(0, m): # per column
        ts = mat[:, i];

        use_SM = False;
        slope = 0.0;
        intercept = 0.0;

        if use_SM:
            model = sm.GLS(np.array(ts[startidx:minidx]), range(startidx, minidx));
            model_fit = model.fit();
            slope = model_fit.params[0];
            intercept = model_fit.scale;
        else:
            model = scistat.linregress(np.array(range(startidx, minidx)), ts[startidx:minidx]);
            slope = model.slope;
            intercept = model.intercept;
        #end if
        slopes[i] = slope;
        intercepts[i] = intercept;

        slope = slope * 1.1;
        
        adjust_intercept = ts[minidx - 1] - ((minidx - 1) * slope + intercept);
        intercept = intercept + adjust_intercept;

        #prediction
        for j in range(minidx, n):
            if (j != minidx) and (((j - minidx) % 8) == 0):
                slope *= 0.85;
                adjust_intercept = ts[j - 1] - ((j - 1) * slope + intercept); # intercept depends on the slope for continuity of prediction
                intercept = intercept + adjust_intercept; # so it has to be re-adjusted if slope is changed
            #end if

            ts[j] = (j * slope) + intercept;
        #end for
        slopes2[i] = slope;
        mat[:, i] = ts;
    #end for
    #np.savetxt("/home/zakhar/ReVival/UDF/py_predict/test.txt", np.array([slopes, slopes2, intercepts]));
    return mat;
#end function



def predict_CD_AR_PY_linear(mat, _param_method = 'cmle',  _param_ic = None,  _param_trend = 'c',  _param_solver = 'lbfgs', _param_cd_k = 0, _param_trans = True, _param_linlag = 30):
    # load dataset
    n = len(mat);
    m = len(mat[0]);

    if (_param_cd_k == 0):
        _param_cd_k = m;

    np.savetxt("/home/zakhar/ReVival/UDF/py_predict/test.txt", np.array([0]));

    minidx = n;
    for i in range(0, m): # per column
        #extract ts
        ts = mat[:, i];
        for j in range(0, len(ts)):
            if np.isnan(ts[j]):
                minidx = min(minidx, j);
                break;

    matpre = mat[0:minidx, :];

    (Load, Rel) = cdnat.native_cd(matpre);
    sigma = np.array(Load[0, :]);

    for i in range(0, len(Load[0])):
        loadcol = Load[:, i];
        sigma[i] = LA.norm(loadcol);
        loadcol = loadcol / sigma[i];
        Load[:, i] = loadcol;

    Load = np.concatenate((Load, [[np.nan] * m] * (n - minidx)))

    for i in range(0, len(Load[0])): # per column
        #extract ts
        ts = Load[:, i];

        #predict
        model = AR(ts[:minidx]);
        model_fit = model.fit(disp = -1, method = _param_method, ic = _param_ic, trend = _param_trend, solver = _param_solver, transparams = _param_trans);
        predres = model_fit.predict(minidx, n-1);

        #put in back
        ts[minidx:] = predres;

        #put in back #2
        Load[:, i] = ts;
    #end for

    # linear regression
    alt_mat = LinearRegressionPrediction(mat, minidx, _param_linlag);

    # AR recon
    mat = np.dot(np.dot(Load, np.diag(sigma)), Rel.T);

    # average the results
    for i in range(0, m):
        ts = mat[minidx:, i];
        meanv = np.mean(ts);
        ts -= meanv;
        ts *= 2.2;
        ts += meanv;
        mat[minidx:, i] = ts;
    
    mat = (mat + alt_mat) / 2;

    return mat;



def predict_CD_AR_PY_linearL(mat, _param_method = 'cmle',  _param_ic = None,  _param_trend = 'c',  _param_solver = 'lbfgs', _param_cd_k = 0, _param_trans = True, _param_linlag = 30):
    # load dataset
    n = len(mat);
    m = len(mat[0]);

    if (_param_cd_k == 0):
        _param_cd_k = m;

    np.savetxt("/home/zakhar/ReVival/UDF/py_predict/test.txt", np.array([0]));

    minidx = n;
    for i in range(0, m): # per column
        #extract ts
        ts = mat[:, i];
        for j in range(0, len(ts)):
            if np.isnan(ts[j]):
                minidx = min(minidx, j);
                break;

    matpre = mat[0:minidx, :];

    (Load, Rel) = cdnat.native_cd(matpre);
    sigma = np.array(Load[0, :]);

    for i in range(0, len(Load[0])):
        loadcol = Load[:, i];
        sigma[i] = LA.norm(loadcol);
        loadcol = loadcol / sigma[i];
        Load[:, i] = loadcol;

    Load = np.concatenate((Load, [[np.nan] * m] * (n - minidx)))
    alt_Load = LinearRegressionPrediction(Load, minidx, _param_linlag);

    for i in range(0, len(Load[0])): # per column
        #extract ts
        ts = Load[:, i];

        #predict
        model = AR(ts[:minidx]);
        model_fit = model.fit(disp = -1, method = _param_method, ic = _param_ic, trend = _param_trend, solver = _param_solver, transparams = _param_trans);
        predres = model_fit.predict(minidx, n-1);

        #put in back
        ts[minidx:] = predres;

        #put in back #2
        Load[:, i] = ts;
    #end for

    # linear regression
    alt_mat = LinearRegressionPrediction(mat, minidx, _param_linlag);

    # average the results
    for i in range(0, m):
        ts = Load[minidx:, i];
        meanv = np.mean(ts);
        ts -= meanv;
        ts *= 2.5;
        ts += meanv;
        Load[minidx:, i] = ts;
    
    Load = (Load + alt_Load) / 2;

    # AR recon
    mat = np.dot(np.dot(Load, np.diag(sigma)), Rel.T);

    mat = (mat + alt_mat) / 2;

    return mat;
#end function


def predict_CD_AR_PY_trend(mat, _param_method = 'cmle',  _param_ic = None,  _param_trend = 'c',  _param_solver = 'lbfgs', _param_cd_k = 0, _param_trans = True, _param_linlag = 30):
    # load dataset
    n = len(mat);
    m = len(mat[0]);

    if (_param_cd_k == 0):
        _param_cd_k = m;

    np.savetxt("/home/zakhar/ReVival/UDF/py_predict/test.txt", mat);

    minidx = n;
    for i in range(0, m): # per column
        #extract ts
        ts = mat[:, i];
        for j in range(0, len(ts)):
            if np.isnan(ts[j]):
                minidx = min(minidx, j);
                break;

    matpre = mat[0:minidx, :];

    #NEW#
    trendskip = 50;
    minidx = minidx - trendskip;
    decomposition = seasonal_decompose(matpre, freq=trendskip+1, two_sided=False);
    trend = decomposition.trend;
    trendlen = len(trend) - np.isnan(trend).sum() / m;
    trend = trend[~np.isnan(trend)].reshape(trendlen, m);
    matpre = matpre[trendskip:, :];
    matpre = matpre - trend;
    #END-NEW#
    ##
    # by the end of this block we have a matrix trend (m x n-trendskip)
    # which contains extracted trend, while matpre is cut and has this trend subtracted
    ##

    (Load, Rel) = cdnat.native_cd(matpre);
    sigma = np.array(Load[0, :]);

    for i in range(0, len(Load[0])):
        loadcol = Load[:, i];
        sigma[i] = LA.norm(loadcol);
        loadcol = loadcol / sigma[i];
        Load[:, i] = loadcol;

    Load = np.concatenate((Load, [[np.nan] * m] * (n - minidx - trendskip)));

    for i in range(0, len(Load[0])): # per column
        #extract ts
        ts = Load[:, i];

        #predict
        model = AR(ts[:minidx]);
        model_fit = model.fit(disp = -1, method = _param_method, ic = _param_ic, trend = _param_trend, solver = _param_solver, transparams = _param_trans);
        predres = model_fit.predict(minidx, n - trendskip - 1);

        #put in back
        ts[minidx:] = predres;

        #put in back #2
        Load[:, i] = ts;
    #end for

    #NEW#
    trend = np.concatenate((trend, [[np.nan] * m] * (n - minidx - trendskip)))
    for i in range(0, len(trend[0])): # per column
        #extract ts
        ts = trend[:, i];

        #predict
        model = AR(ts[:minidx]);
        model_fit = model.fit(disp = -1, method = _param_method, ic = _param_ic, trend = _param_trend, solver = _param_solver, transparams = _param_trans);
        predres = model_fit.predict(minidx, n - trendskip - 1);

        #put in back
        ts[minidx:] = predres;

        #put in back #2
        trend[:, i] = ts;
    #end for
    #END#

    # AR recon
    matnew = np.dot(np.dot(Load, np.diag(sigma)), Rel.T);
    matnew = matnew + trend;
    mat = np.concatenate((mat[:trendskip, :], matnew));

    # linear regression
    minidx = minidx + trendskip; # restore after trending
    alt_mat = LinearRegressionPrediction(mat.copy(), minidx, _param_linlag);

    if False:
        # average the results
        for i in range(0, m):
            ts = mat[minidx:, i];
            meanv = np.mean(ts);
            ts -= meanv;
            ts *= 2.2;
            ts += meanv;
            mat[minidx:, i] = ts;
        #end for
        mat = (mat + alt_mat) / 2;

    fulltrend = np.concatenate((mat[:trendskip, :], trend));
    return mat;
#end function


def predict_CD_AR_PY_trendL(mat, _param_method = 'cmle',  _param_ic = None,  _param_trend = 'c',  _param_solver = 'lbfgs', _param_cd_k = 0, _param_trans = True, _param_linlag = 30):
    # load dataset
    n = len(mat);
    m = len(mat[0]);

    if (_param_cd_k == 0):
        _param_cd_k = m;

    #np.savetxt("/home/zakhar/ReVival/UDF/py_predict/test.txt", mat);

    minidx = n;

    for i in range(0, n): # per row
        #extract row
        row = mat[i, :];
        anyPresent = False;
        for j in range(0, len(row)):
            if not np.isnan(row[j]):
                anyPresent = True;
            #end if
        #end for
        if not anyPresent:
            minidx = min(minidx, i);
            break;

    matpre = mat[0:minidx, :];
    matpre = cdnat.native_recovery_param(matpre, min(5, m-1), 1E-5, False, 0);

    (Load, Rel) = cdnat.native_cd(matpre);
    sigma = np.array(Load[0, :]);

    for i in range(0, len(Load[0])):
        loadcol = Load[:, i];
        sigma[i] = LA.norm(loadcol);
        loadcol = loadcol / sigma[i];
        Load[:, i] = loadcol;

    Load_bak = Load.copy();
    #NEW#
    trendskip = 30;
    minidx = minidx - trendskip;
    decomposition = seasonal_decompose(Load, freq=trendskip+1, two_sided=False);
    trend = decomposition.trend;
    trendlen = len(trend) - np.isnan(trend).sum() / m;
    trend = trend[~np.isnan(trend)].reshape(trendlen, m);
    Load = Load[trendskip:, :];
    Load = Load - trend;
    #END-NEW#
    ##
    # by the end of this block we have a matrix trend (m x n-trendskip)
    # which contains extracted trend, while matpre is cut and has this trend subtracted
    ##

    Load = np.concatenate((Load, [[np.nan] * m] * (n - minidx - trendskip)));

    for i in range(0, len(Load[0])): # per column
        #extract ts
        ts = Load[:, i];

        #predict
        model = AR(ts[:minidx]);
        model_fit = model.fit(disp = -1, method = _param_method, ic = _param_ic, trend = _param_trend, solver = _param_solver, transparams = _param_trans);
        predres = model_fit.predict(minidx, n - trendskip - 1);

        #put in back
        ts[minidx:] = predres;

        #put in back #2
        Load[:, i] = ts;
    #end for

    #NEW#
    trend = np.concatenate((trend, [[np.nan] * m] * (n - minidx - trendskip)))
    for i in range(0, len(trend[0])): # per column
        #extract ts
        ts = trend[:, i];

        #predict
        model = AR(ts[:minidx]);
        model_fit = model.fit(disp = -1, method = _param_method, ic = _param_ic, trend = _param_trend, solver = _param_solver, transparams = _param_trans);
        predres = model_fit.predict(minidx, n - trendskip - 1);

        #put in back
        ts[minidx:] = predres;

        #put in back #2
        trend[:, i] = ts;
    #end for
    #END#

    # AR recon
    Load = Load + trend;
    
    Load = np.concatenate((Load_bak[:trendskip,:], Load));
    matnew = np.dot(np.dot(Load, np.diag(sigma)), Rel.T);
    mat = matnew;

    # linear regression
    minidx = minidx + trendskip; # restore after trending
    #alt_mat = LinearRegressionPrediction(mat.copy(), minidx, _param_linlag);

    if False:
        # average the results
        for i in range(0, m):
            ts = mat[minidx:, i];
            meanv = np.mean(ts);
            ts -= meanv;
            ts *= 2.2;
            ts += meanv;
            mat[minidx:, i] = ts;
        #end for
        mat = (mat + alt_mat) / 2;

    fulltrend = np.concatenate((mat[:trendskip, :], trend));
    return mat;
#end function


if __name__ == "__main__":
    main()
