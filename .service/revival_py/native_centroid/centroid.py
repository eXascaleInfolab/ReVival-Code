#!/usr/bin/python

import os.path as __os_path_import;
import ctypes as __native_c_types_import;
import numpy as __numpy_import;

__NATIVE_CENTROID_LIBRARY_PATH_DEBUG = "libIncCDMdb.so";
__NATIVE_CENTROID_LIBRARY_PATH = "libIncCDMdb.so";
__NATIVE_CENTROID_LIBRARY_PATH_ALT = "/usr/local/lib/libIncCDMdb.so";

__ctype_libcd_native = None;

if __os_path_import.isfile(__NATIVE_CENTROID_LIBRARY_PATH_DEBUG):
    __ctype_libcd_native = __native_c_types_import.cdll.LoadLibrary(
        __NATIVE_CENTROID_LIBRARY_PATH_DEBUG);

elif __os_path_import.isfile(__NATIVE_CENTROID_LIBRARY_PATH):
    __ctype_libcd_native = __native_c_types_import.cdll.LoadLibrary(
        __NATIVE_CENTROID_LIBRARY_PATH);

elif __os_path_import.isfile(__NATIVE_CENTROID_LIBRARY_PATH_ALT):
    __ctype_libcd_native = __native_c_types_import.cdll.LoadLibrary(
        __NATIVE_CENTROID_LIBRARY_PATH_ALT);

else:
    print "Cannot load the shared library - file not found";
    raise Exception('Failed to load the shared library.');

#endif


def native_cd(__py_matrix):
    # type: (__numpy_import.array) -> [__numpy_import.array, __numpy_import.array]
    """
    Performs Centroid Decomposition of a matrix.
    :param __py_matrix: 2D array
    :return: (Load, Rel) matrices such that Load * Rel^T ~= X
    """
    __py_sizen = len(__py_matrix);
    __py_sizem = len(__py_matrix[0]);

    __ctype_sizen = __native_c_types_import.c_ulonglong(__py_sizen);
    __ctype_sizem = __native_c_types_import.c_ulonglong(__py_sizem);

    # Native code uses linear matrix layout, and also it's easier to pass it in like this
    __py_input_flat = __numpy_import.ndarray.flatten(__py_matrix);
    __ctype_input_matrix = __numpy_import.ctypeslib.as_ctypes(__py_input_flat);

    # Containers which will be filled from native code, instead of returning anything, also flat
    __ctype_load_container = __numpy_import.ctypeslib.as_ctypes(
        __numpy_import.zeros(__py_sizen * __py_sizem));
    __ctype_rel_container = __numpy_import.ctypeslib.as_ctypes(
        __numpy_import.zeros(__py_sizem * __py_sizem));

    # extern "C" void
    # centroidDecomposition(
    #         double *matrixNative, size_t dimN, size_t dimM,
    #         double *loadContainer, double *relContainer,
    # )
    __ctype_libcd_native.centroidDecomposition(
        __ctype_input_matrix, __ctype_sizen, __ctype_sizem,
        __ctype_load_container, __ctype_rel_container);

    __py_load_matrix = __numpy_import.array(__ctype_load_container).reshape(__py_sizen, __py_sizem);
    __py_rel_matrix = __numpy_import.array(__ctype_rel_container).reshape(__py_sizem, __py_sizem);

    return __py_load_matrix, __py_rel_matrix;

# end function


def native_cd_truncated(__py_matrix, __py_rank):
    # type: (__numpy_import.array, int) -> (__numpy_import.array, __numpy_import.array)
    """
    Performs Centroid Decomposition of a matrix.
    :param __py_matrix: 2D array
    :param __py_rank: truncation rank
    :return:  (Load, Rel) matrices such that Load * Rel^T ~= X
    """
    __py_sizen = len(__py_matrix);
    __py_sizem = len(__py_matrix[0]);

    assert (__py_rank >= 0);
    assert (__py_rank <= __py_sizem);

    __ctype_sizen = __native_c_types_import.c_ulonglong(__py_sizen);
    __ctype_sizem = __native_c_types_import.c_ulonglong(__py_sizem);
    __ctype_rank = __native_c_types_import.c_ulonglong(__py_rank);

    # Native code uses linear matrix layout, and also it's easier to pass it in like this
    __py_input_flat = __numpy_import.ndarray.flatten(__py_matrix);
    __ctype_input_matrix = __numpy_import.ctypeslib.as_ctypes(__py_input_flat);

    # Containers which will be filled from native code, instead of returning anything, also flat
    __ctype_load_container = __numpy_import.ctypeslib.as_ctypes(
        __numpy_import.zeros(__py_sizen * __py_sizem));
    __ctype_rel_container = __numpy_import.ctypeslib.as_ctypes(
        __numpy_import.zeros(__py_sizem * __py_sizem));

    # extern "C" void
    # centroidDecompositionTruncated(
    #         double *matrixNative, size_t dimN, size_t dimM,
    #         double *loadContainer, double *relContainer,
    #         size_t truncation
    # )
    __ctype_libcd_native.centroidDecompositionTruncated(
        __ctype_input_matrix, __ctype_sizen, __ctype_sizem,
        __ctype_load_container, __ctype_rel_container,
        __ctype_rank
    );

    __py_load_matrix = __numpy_import.array(__ctype_load_container).reshape(__py_sizen, __py_sizem);
    __py_rel_matrix = __numpy_import.array(__ctype_rel_container).reshape(__py_sizem, __py_sizem);

    return __py_load_matrix, __py_rel_matrix;

# end function


def native_recovery(__py_matrix, __py_rank, __py_eps):
    # type: (__numpy_import.array, int, float) -> __numpy_import.array
    """
    Recovers missing values (designated as NaN) in a matrix
    :param __py_matrix: 2D array
    :param __py_rank: truncation rank to be used (0 = detect truncation automatically)
    :param __py_eps: threshold for difference during recovery
    :return: 2D array recovered matrix
    """
    __py_sizen = len(__py_matrix);
    __py_sizem = len(__py_matrix[0]);

    assert (__py_rank >= 0);
    assert (__py_rank < __py_sizem);

    __ctype_sizen = __native_c_types_import.c_ulonglong(__py_sizen);
    __ctype_sizem = __native_c_types_import.c_ulonglong(__py_sizem);
    __ctype_rank = __native_c_types_import.c_ulonglong(__py_rank);
    __ctype_eps = __native_c_types_import.c_double(__py_eps);

    # Native code uses linear matrix layout, and also it's easier to pass it in like this
    __py_input_flat = __numpy_import.ndarray.flatten(__py_matrix);
    __ctype_input_matrix = __numpy_import.ctypeslib.as_ctypes(__py_input_flat);

    # extern "C" void
    # recoveryOfMissingValues(
    #         double *matrixNative, size_t dimN, size_t dimM,
    #         size_t truncation, double epsilon
    # )
    __ctype_libcd_native.recoveryOfMissingValues(
        __ctype_input_matrix, __ctype_sizen, __ctype_sizem,
        __ctype_rank, __ctype_eps
    );

    __py_recovered = __numpy_import.array(__ctype_input_matrix).reshape(__py_sizen, __py_sizem);

    return __py_recovered;

# end function

def native_recovery_param(__py_matrix, __py_rank, __py_eps, __py_normalize, __py_optimization):
    return native_recovery_xtra(
        __py_matrix, __py_rank, __py_eps, __py_normalize, __py_optimization, "default");


def native_recovery_xtra(__py_matrix, __py_rank, __py_eps, __py_normalize, __py_optimization, __py_signvectorstrategy):
    # type: (__numpy_import.array, int, float, bool, int, string) -> __numpy_import.array
    """
    Recovers missing values (designated as NaN) in a matrix. Supports additional parameters
    :param __py_matrix: 2D array
    :param __py_rank: truncation rank to be used (0 = detect truncation automatically)
    :param __py_eps: threshold for difference during recovery
    :param __py_normalize: flag whether to use normalization before the recovery
    :param __py_optimization: optimization code (0 = no optimization)
    :param __py_signvectorstrategy: sign vector strategy ("default" will use the default one of the library)
    :return: 2D array recovered matrix
    """
    __py_sizen = len(__py_matrix);
    __py_sizem = len(__py_matrix[0]);

    assert (__py_rank >= 0);
    assert (__py_rank < __py_sizem);
    assert (__py_optimization >= 0);

    __py_strategycode = -1;
    if str.lower(__py_signvectorstrategy) == "default":
        __py_strategycode = 0;
    elif str.lower(__py_signvectorstrategy) == "issv-base":
        __py_strategycode = 1;
    elif str.lower(__py_signvectorstrategy) == "issv+-base":
        __py_strategycode = 2;
    elif str.lower(__py_signvectorstrategy) == "issv-init":
        __py_strategycode = 11;
    elif str.lower(__py_signvectorstrategy) == "issv+-init":
        __py_strategycode = 12;
    elif str.lower(__py_signvectorstrategy) == "lsv-base":
        __py_strategycode = 21;
    elif str.lower(__py_signvectorstrategy) == "lsv-noinit":
        __py_strategycode = 22;

    assert (__py_strategycode != -1);

    __ctype_sizen = __native_c_types_import.c_ulonglong(__py_sizen);
    __ctype_sizem = __native_c_types_import.c_ulonglong(__py_sizem);
    __ctype_rank = __native_c_types_import.c_ulonglong(__py_rank);
    __ctype_eps = __native_c_types_import.c_double(__py_eps);
    
    if __py_normalize:
        __ctype_normalize = __native_c_types_import.c_ulonglong(1);
    else:
        __ctype_normalize = __native_c_types_import.c_ulonglong(0);
    # end if

    __ctype_optimization = __native_c_types_import.c_ulonglong(__py_optimization);
    __ctype_strategycode = __native_c_types_import.c_ulonglong(__py_strategycode);

    # Native code uses linear matrix layout, and also it's easier to pass it in like this
    __py_input_flat = __numpy_import.ndarray.flatten(__py_matrix);
    __ctype_input_matrix = __numpy_import.ctypeslib.as_ctypes(__py_input_flat);

    # extern "C" void
    # recoveryOfMissingValuesParametrized(
    #         double *matrixNative, size_t dimN, size_t dimM,
    #         size_t truncation, double epsilon,
    #         size_t useNormalization, size_t optimization,
    #         size_t signVectorStrategyCode
    # )
    __ctype_libcd_native.recoveryOfMissingValuesParametrized(
        __ctype_input_matrix, __ctype_sizen, __ctype_sizem,
        __ctype_rank, __ctype_eps,
        __ctype_normalize, __ctype_optimization,
        __ctype_strategycode
    );

    __py_recovered = __numpy_import.array(__ctype_input_matrix).reshape(__py_sizen, __py_sizem);

    return __py_recovered;

# end function
