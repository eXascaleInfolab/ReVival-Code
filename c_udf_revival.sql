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
DROP FUNCTION IF EXISTS centroid_recovery_revival;
CREATE FUNCTION centroid_recovery_revival(
        sid_in INTEGER, dt_in TIMESTAMP, tsval FLOAT,
        trunc INTEGER, eps FLOAT
    )
RETURNS TABLE(
        series_id INTEGER, datetime TIMESTAMP, value_recovered FLOAT
    )
LANGUAGE CPP
{
    #pragma LDFLAGS -ldl
    #include <dlfcn.h>
    #include <cmath>
    #include <map>
    #define CENTROID_LIBRARY_PATH "libIncCD.so"

    //-- verify basic integrity of the data
    if (dt_in.count != sid_in.count || dt_in.count != tsval.count)
    {
        return const_cast<char *>("centroid_recovery_revival/5(error) : invalid cardinality of time series, ids/timestamps/values are not aligned");
    }

    //-- special case when no values are given
    if (dt_in.count == 0)
    {
        series_id->initialize(series_id, 0);
        datetime->initialize(datetime, 0);
        value_recovered->initialize(value_recovered, 0);
        return NULL;
    }

    //-- additional integrity check
    if (eps.count <= 0 || trunc.count <= 0)
    {
        return const_cast<char *>("centroid_recovery_revival/5(error) : invalid cardinality of config parameters, eps or trunc are empty");
    }
    
    //-- init data, this is exactly enough to fit all the data passed inside
    size_t total = dt_in.count;
    double *_data = (double *)malloc(sizeof(double) * total);

    //-- calculate m & verify integrity of the data

    std::map<int, size_t> id2idx;
    std::map<int, size_t> id2size;

    auto getidx = [&](int x) -> size_t { return id2idx.find(x)->second; };
    auto getrowidx = [&](int x) -> size_t & { return id2size.find(x)->second; };

    for (size_t i = 0; i < total; ++i)
    {
        int sid = sid_in.data[i];
        auto iter = id2size.find(sid);

        if (iter != id2size.end())
        {
            iter->second++;
        }
        else
        {
            id2idx.emplace(std::make_pair(sid, id2size.size()));
            id2size.emplace(std::make_pair(sid, 1));
        }
    }

    size_t m = id2idx.size();
    size_t n = id2size.begin()->second;

    for (auto iter = id2size.begin(); iter != id2size.end(); ++iter)
    {
        if (iter->second != n)
        {
            free(_data);
            return const_cast<char *>("centroid_recovery_revival/5(error) : invalid time series input, series contain dirrefent number of values");
        }
    }

    //-- step 2 - iterate over data and fill the matrix

    for (size_t it = 0; it < total; ++it)
    {
        size_t j = getidx(sid_in.data[it]);
        size_t i = (n - getrowidx(sid_in.data[it])--);

        _data[m * i + j] = tsval.is_null(tsval.data[it]) ? NAN : tsval.data[it];
    }

    //-- cd params
    size_t truncation = trunc.data[0];
    double epsilon = eps.data[0];
    size_t norm = (size_t)true, //-- normalize, since we always have raw data
           opt = 0, //-- code 0, no additional optimizations
           svs = 22; //-- 22 = forced LSV-no-init to match ReVival impl.

    //-- recover with external call to cd
    /**/#ifdef CENTROID_LIBRARY_PATH
    void *cdlib = dlopen(CENTROID_LIBRARY_PATH, RTLD_LAZY);
    /**/#else
    void *cdlib = dlopen("libIncCD.so", RTLD_LAZY);
    /**/#endif
    if (cdlib == nullptr)
    {
        free(_data);
        return const_cast<char *>("centroid_recovery_revival/5(error) : cannot open shared library with CD implementation");
    }
    void (*rcdfuncptr)(double*,size_t,size_t,size_t,double,size_t,size_t,size_t);
    *((void **)&rcdfuncptr) = dlsym(cdlib, "recoveryOfMissingValuesParametrized");

    rcdfuncptr(_data, n, m,
        truncation, epsilon,
        norm, opt, svs
    ); //-- no allocations that are not freed here

    dlclose(cdlib);

    //-- populate return values

    series_id->initialize(series_id, total);
    datetime->initialize(datetime, total);
    value_recovered->initialize(value_recovered, total);

    for (size_t it = 0; it < total; ++it)
    {
        size_t j = getidx(sid_in.data[it]);
        size_t i = getrowidx(sid_in.data[it])++;

        series_id->data[it] = sid_in.data[it];
        datetime->data[it] = dt_in.data[it];
        value_recovered->data[it] = _data[m * i + j];
    }

    //--cleanup
    free(_data);
    return NULL;
};

-- example

-- selector for a few time series, they already contain nulls in this table
SELECT series_id, sys.epoch(datetime) as timeval, value FROM hourly
WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
    AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000));

-- same selector, but with a pass-through of centroid_recovery_revival/5 to recover all nulls
SELECT series_id, sys.epoch(datetime) as timeval, value_recovered FROM centroid_recovery_revival((
    SELECT series_id, datetime, value, 1, 0.01 FROM hourly
    WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
        AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
));

-- output of the example:

/*
zakhar@zakhar-Aspire-V3-772:~/ReVival$ mcl.exe -d pytest
Welcome to mclient, the MonetDB/SQL interactive terminal (Aug2018-SP1)
Database: MonetDB v11.31.11 (Aug2018-SP1), 'mapi:monetdb://zakhar-Aspire-V3-772:50000/pytest'
Type \q to quit, \? for a list of available commands
auto commit mode: on
sql>SELECT series_id, sys.epoch(datetime) as timeval, value FROM hourly
more>WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
more>    AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000));
+-----------+-----------+--------------------------+
| series_id | timeval   | value                    |
+===========+===========+==========================+
|      2112 | 126554400 |              8.934333333 |
|      2112 | 126576000 |                    8.905 |
|      2112 | 126597600 |                      8.9 |
|      2112 | 126619200 |                     null | <----
|      2112 | 126640800 |                     null | <----
|      2112 | 126662400 |                     null | <----
|      2112 | 126684000 |                    8.896 |
|      2112 | 126705600 |                    8.902 |
|      2112 | 126727200 |                  8.94725 |
|      2112 | 126748800 |                    8.916 |
|      2181 | 126554400 |              5.689142857 |
|      2181 | 126576000 |              5.746857143 |
|      2181 | 126597600 |                   5.7262 |
|      2181 | 126619200 |              5.700666667 |
|      2181 | 126640800 |                   5.6768 |
|      2181 | 126662400 |                   5.7185 |
|      2181 | 126684000 |                    5.709 |
|      2181 | 126705600 |                    5.701 |
|      2181 | 126727200 |                    5.713 |
|      2181 | 126748800 |                    5.727 |
|      2303 | 126554400 |                   3.0595 |
|      2303 | 126576000 |              3.077666667 |
|      2303 | 126597600 |              3.061333333 |
|      2303 | 126619200 |                  3.04025 |
|      2303 | 126640800 |              3.035333333 |
|      2303 | 126662400 |              3.046666667 |
|      2303 | 126684000 |                    3.048 |
|      2303 | 126705600 |                     3.04 |
|      2303 | 126727200 |              3.076666667 |
|      2303 | 126748800 |              3.096583333 |
+-----------+-----------+--------------------------+
30 tuples
sql>SELECT series_id, sys.epoch(datetime) as timeval, value_recovered FROM centroid_recovery_revival((
more>    SELECT series_id, datetime, value, 0.01, 1 FROM hourly
more>    WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
more>        AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
more>));
+-----------+-----------+--------------------------+
| series_id | timeval   | value_recovered          |
+===========+===========+==========================+
|      2112 | 126554400 |              8.934333333 |
|      2112 | 126576000 |                    8.905 |
|      2112 | 126597600 |                      8.9 |
|      2112 | 126619200 |         8.89433046471017 | <----
|      2112 | 126640800 |        8.882564907720898 | <----
|      2112 | 126662400 |        8.901940556184815 | <----
|      2112 | 126684000 |                    8.896 |
|      2112 | 126705600 |                    8.902 |
|      2112 | 126727200 |                  8.94725 |
|      2112 | 126748800 |                    8.916 |
|      2181 | 126554400 |              5.689142857 |
|      2181 | 126576000 |              5.746857143 |
|      2181 | 126597600 |                   5.7262 |
|      2181 | 126619200 |              5.700666667 |
|      2181 | 126640800 |                   5.6768 |
|      2181 | 126662400 |                   5.7185 |
|      2181 | 126684000 |                    5.709 |
|      2181 | 126705600 |                    5.701 |
|      2181 | 126727200 |                    5.713 |
|      2181 | 126748800 |                    5.727 |
|      2303 | 126554400 |                   3.0595 |
|      2303 | 126576000 |              3.077666667 |
|      2303 | 126597600 |              3.061333333 |
|      2303 | 126619200 |                  3.04025 |
|      2303 | 126640800 |              3.035333333 |
|      2303 | 126662400 |              3.046666667 |
|      2303 | 126684000 |                    3.048 |
|      2303 | 126705600 |                     3.04 |
|      2303 | 126727200 |              3.076666667 |
|      2303 | 126748800 |              3.096583333 |
+-----------+-----------+--------------------------+
30 tuples
*/

-- more advanced example

-- selector for a few time series, but afticially changing the values (can be set to null)
SELECT series_id, sys.epoch(datetime) as timeval,
    CASE
        WHEN series_id = 2112 AND (datetime BETWEEN sys.epoch(126597600) AND sys.epoch(126662400)) THEN value + 10
        WHEN series_id = 2303 AND (datetime BETWEEN sys.epoch(126684000) AND sys.epoch(126748800)) THEN value + 10
        ELSE value
    END AS value
FROM hourly
WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303 OR false)
    AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000));

-- all recovered
SELECT series_id, sys.epoch(datetime) as timeval, value_recovered FROM centroid_recovery_revival((
    SELECT series_id, datetime,
        CASE
            WHEN series_id = 2112 AND (datetime BETWEEN sys.epoch(126597600) AND sys.epoch(126662400)) THEN null
            WHEN series_id = 2303 AND (datetime BETWEEN sys.epoch(126684000) AND sys.epoch(126748800)) THEN null
            ELSE value
        END AS value,
        1, 0.01
    FROM hourly
    WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
        AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
));

-- version that also returns runtime

SET SCHEMA sys;
DROP FUNCTION IF EXISTS centroid_recovery_revival_runtime;
CREATE FUNCTION centroid_recovery_revival_runtime(
        sid_in INTEGER, dt_in TIMESTAMP, tsval FLOAT,
        trunc INTEGER, eps FLOAT
    )
RETURNS TABLE(
        series_id INTEGER, datetime TIMESTAMP, value_recovered FLOAT, runtime INTEGER
    )
LANGUAGE CPP
{
    #pragma LDFLAGS -ldl
    #include <dlfcn.h>
    #include <cmath>
    #include <map>
    #include <chrono>
    //#define CENTROID_LIBRARY_PATH "libIncCD.so"
    #define CENTROID_LIBRARY_PATH "/home/zakhar/MVR/CentroidCentral/CD_tool/cmake-build-debug/libIncCD.so"

    //-- verify basic integrity of the data
    if (dt_in.count != sid_in.count || dt_in.count != tsval.count)
    {
        return const_cast<char *>("centroid_recovery_revival_runtime/5(error) : invalid cardinality of time series, ids/timestamps/values are not aligned");
    }

    //-- special case when no values are given
    if (dt_in.count == 0)
    {
        series_id->initialize(series_id, 0);
        datetime->initialize(datetime, 0);
        value_recovered->initialize(value_recovered, 0);
        return NULL;
    }

    //-- additional integrity check
    if (eps.count <= 0 || trunc.count <= 0)
    {
        return const_cast<char *>("centroid_recovery_revival_runtime/5(error) : invalid cardinality of config parameters, eps or trunc are empty");
    }
    
    //-- init data, this is exactly enough to fit all the data passed inside
    size_t total = dt_in.count;
    double *_data = (double *)malloc(sizeof(double) * total);

    //-- calculate m & verify integrity of the data

    std::map<int, size_t> id2idx;
    std::map<int, size_t> id2size;

    auto getidx = [&](int x) -> size_t { return id2idx.find(x)->second; };
    auto getrowidx = [&](int x) -> size_t & { return id2size.find(x)->second; };

    for (size_t i = 0; i < total; ++i)
    {
        int sid = sid_in.data[i];
        auto iter = id2size.find(sid);

        if (iter != id2size.end())
        {
            iter->second++;
        }
        else
        {
            id2idx.emplace(std::make_pair(sid, id2size.size()));
            id2size.emplace(std::make_pair(sid, 1));
        }
    }

    size_t m = id2idx.size();
    size_t n = id2size.begin()->second;

    for (auto iter = id2size.begin(); iter != id2size.end(); ++iter)
    {
        if (iter->second != n)
        {
            free(_data);
            return const_cast<char *>("centroid_recovery_revival_runtime/5(error) : invalid time series input, series contain dirrefent number of values");
        }
    }

    //-- step 2 - iterate over data and fill the matrix

    for (size_t it = 0; it < total; ++it)
    {
        size_t j = getidx(sid_in.data[it]);
        size_t i = (n - getrowidx(sid_in.data[it])--);

        _data[m * i + j] = tsval.is_null(tsval.data[it]) ? NAN : tsval.data[it];
    }

    //-- cd params
    size_t truncation = trunc.data[0];
    double epsilon = eps.data[0];
    size_t norm = (size_t)true, //-- normalize, since we always have raw data
           opt = 0, //-- code 0, no additional optimizations
           svs = 22; //-- 22 = forced LSV-no-init to match ReVival impl.

    //-- recover with external call to cd
    /**/#ifdef CENTROID_LIBRARY_PATH
    void *cdlib = dlopen(CENTROID_LIBRARY_PATH, RTLD_LAZY);
    /**/#else
    void *cdlib = dlopen("libIncCD.so", RTLD_LAZY);
    /**/#endif
    if (cdlib == nullptr)
    {
        free(_data);
        return const_cast<char *>("centroid_recovery_revival_runtime/5(error) : cannot open shared library with CD implementation");
    }
    void (*rcdfuncptr)(double*,size_t,size_t,size_t,double,size_t,size_t,size_t);
    *((void **)&rcdfuncptr) = dlsym(cdlib, "recoveryOfMissingValuesParametrized");

    std::chrono::steady_clock::time_point begin;
    std::chrono::steady_clock::time_point end;
    begin = std::chrono::steady_clock::now();
    rcdfuncptr(_data, n, m,
        truncation, epsilon,
        norm, opt, svs
    ); //-- no allocations that are not freed here
    end = std::chrono::steady_clock::now();
    int64_t rt_result = std::chrono::duration_cast<std::chrono::microseconds>(end - begin).count();

    dlclose(cdlib);

    //-- populate return values

    series_id->initialize(series_id, total);
    datetime->initialize(datetime, total);
    value_recovered->initialize(value_recovered, total);
    runtime->initialize(runtime, total);

    for (size_t it = 0; it < total; ++it)
    {
        size_t j = getidx(sid_in.data[it]);
        size_t i = getrowidx(sid_in.data[it])++;

        series_id->data[it] = sid_in.data[it];
        datetime->data[it] = dt_in.data[it];
        value_recovered->data[it] = _data[m * i + j];
        runtime->data[it] = rt_result;
    }

    //--cleanup
    free(_data);
    return NULL;
};

-- examples

SET SCHEMA data;

-- selector for a few time series, they already contain nulls in this table
SELECT series_id, sys.epoch(datetime) as timeval, value FROM hourly
WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
    AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000));

-- same selector, but with a pass-through of centroid_recovery_revival_runtime/5 to recover all nulls
SELECT series_id, sys.epoch(datetime) as timeval, value_recovered, runtime FROM sys.centroid_recovery_revival_runtime((
    SELECT series_id, datetime, value, 1, 0.01 FROM hourly
    WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
        AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
));

-- selector for a few time series, but afticially changing the values (can be set to null)
SELECT series_id, sys.epoch(datetime) as timeval,
    CASE
        WHEN series_id = 2112 AND (datetime BETWEEN sys.epoch(126597600) AND sys.epoch(126662400)) THEN value + 10
        WHEN series_id = 2303 AND (datetime BETWEEN sys.epoch(126684000) AND sys.epoch(126748800)) THEN value + 10
        ELSE value
    END AS value
FROM hourly
WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303 OR false)
    AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000));

-- all recovered
SELECT series_id, sys.epoch(datetime) as timeval, value_recovered, runtime FROM sys.centroid_recovery_revival_runtime((
    SELECT series_id, datetime,
        CASE
            WHEN series_id = 2112 AND (datetime BETWEEN sys.epoch(126597600) AND sys.epoch(126662400)) THEN null
            WHEN series_id = 2303 AND (datetime BETWEEN sys.epoch(126684000) AND sys.epoch(126748800)) THEN null
            ELSE value
        END AS value,
        1, 0.01
    FROM hourly
    WHERE (series_id = 2112 OR series_id = 2181 OR series_id = 2303)
        AND (datetime >= sys.epoch(126554400000) AND datetime <= sys.epoch(126748800000))
));
