/**
 * This file is copy-pasted php code, regex-adapted to javascript.
 * forgive me, Linus, for I have sinned...
 */

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        Algebra core functions                            //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


/** Performs the recovery of missing values on input X, missing values are only present in base series.
 *  Recovery is performed until threshold of mean square difference is reached or 100 iterations.
 *  Uses truncated CD with a truncation factor of k.
 * @param x : array of arrays | matrix to be recovered
 * @param base_series_index : int | index of base series
 * @param threshold : float | limit of meansquarediff between iteration
 * @param k : int | truncation factor for CD
 * @return array : array | where [0] = X with recovered values; [1] = Int, number of iterations recovery took
 */
function RMV(x, base_series_index, threshold, k) {
    let n = x.length; // number of rows
    let m = x[0].length; // number of columns

    if (k >= m) k = 1;

    // write out all indexes of missing values in the base series
    let missing_value_indices = [];
    for (let i = 0; i < n; i++) {
        if (x[i][base_series_index] === null) {
            missing_value_indices.push(i);
        }
    }

    if (missing_value_indices.length === 0) {
        return [x, 0];
    }

    // initialize missing values with linear interpolation (nearest neighbour for edge values)
    linear_interpolated_base_series_values(x, base_series_index);

    let l = init_array(n, k, 0);
    let r = init_array(m, k, 0);

    let z_all = [];
    let z;
    for (let j = 0; j < k; j++) {
        z = [];
        for (let i = 0; i < n; i++) {
            z.push(1.0);
        }
        z_all.push(z);
    }

    let diff = 99.0;
    let iters = 0;

    while (diff >= threshold && iters < 100) {
        cachedTCD(x, z_all, l, r, k);

        let x_reconstruction = matmult_A_BT(l, r);

        // update the "missing values" in X with those of x_truncated & calculate meansquarediff
        diff = 0.0;

        for (let mv = 0; mv < missing_value_indices.length; mv++) {
            let idx = missing_value_indices[mv];
            let oldval = x[idx][base_series_index];
            let newval = x_reconstruction[idx][base_series_index];

            let val = oldval - newval;
            diff += val * val;
            x[idx][base_series_index] = newval;
        }

        diff = Math.sqrt(diff / missing_value_indices.length);
        iters++;
    }

    return [x, iters];
}


/** Performs the recovery of missing values on input X, missing values are only present in base series.
 *  Recovery is performed until threshold of mean square different is reached or 100 iterations.
 *  Uses truncated CD with a truncation factor of k.
 * @param x : array of arrays | matrix to be recovered
 * @param threshold : float | limit of meansquarediff between iteration
 * @param k : int | truncation factor for CD
 * @return array : array | where [0] = X with recovered values; [1] = Int, number of iterations recovery took
 */
function RMV_all(x, threshold, k) {
    let n = x.length; // number of rows
    let m = x[0].length; // number of columns

    if (k >= m) k = 1;

    // write out all indexes of missing values in the base series
    let missing_value_indices = [];
    for (let i = 0; i < n; i++) {
        for (let j = 0; j < m; j++) {
            if (x[i][j] === null) {
                missing_value_indices.push([i, j]);
            }
        }
    }

    if (missing_value_indices.length === 0) {
        return [x, 0];
    }

    // initialize missing values with linear interpolation (nearest neighbour for edge values)
    for (let j = 0; j < m; j++) {
        linear_interpolated_base_series_values(x, j);
    }

    let l = init_array(n, k, 0);
    let r = init_array(m, k, 0);

    let z_all = [];
    for (let j = 0; j < k; j++) {
        let z = [];
        for (let i = 0; i < n; i++) {
            z.push(1.0);
        }
        z_all.push(z);
    }

    let diff = 99.0;
    let iters = 0;

    while (diff >= threshold && iters < 100) {
        cachedTCD(x, z_all, l, r, k);

        let x_reconstruction = matmult_A_BT(l, r);

        // update the "missing values" in X with those of x_truncated & calculate meansquarediff
        diff = 0.0;

        for (let mv = 0; mv < missing_value_indices.length; mv++) {
            let base_series_index = missing_value_indices[mv][1];
            let idx = missing_value_indices[mv][0];

            let oldval = x[idx][base_series_index];
            let newval = x_reconstruction[idx][base_series_index];

            let val = oldval - newval;
            diff += val * val;
            x[idx][base_series_index] = newval;
        }

        diff = Math.sqrt(diff / missing_value_indices.length);
        iters++;
    }

    return [x, iters];
}


/** Performs Batch Centroid decomposition of a matrix x.
 * @param x : array of arrays | a matrix to be decomposed
 * @return array : array | where [0] = L; [1] = R matrices s.t. L*R^T=X; [2] = Z[] sign vectors matrix
 */
function CD(x) {
    let n = x.length;
    let m = x[0].length;

    let z_all = [];

    for (let j = 0; j < m; j++) {
        let z = [];

        for (let i = 0; i < n; i++) {
            z.push(1.0);
        }

        z_all.push(z);
    }

    let l = init_array(n, m);
    let r = init_array(m, m);

    cachedTCD(x, z_all, l, r, m);

    return [l, r, trsp(z_all)];
}


/** Performs Centroid decomposition of a matrix x.
 * @param x_in : array of arrays | a matrix to be decomposed
 * @param z_all : array of arrays | a list of vectors containing all maximizing sign vectors, used as starting point for ISSV+ and updated after the process
 * @param l : array of arrays | loading matrix to be overwritten with the decomposition
 * @param r : array of arrays | relevance matrix to be overwritten with the decomposition
 * @param truncation : int | limit of the amount of columns which are calculated
 * @return void
 */
function cachedTCD(x_in, z_all, l, r, truncation) {
    // deep copy, since x_in is always a reference
    let x = matrix_clone(x_in);

    let n = x.length; // number of rows
    let m = x[0].length; // number of columns

    if (m < truncation) die("Incorrect truncation value (truncation) for matrix X { n=n, m=m } in function cachedTCD");

    let c_column = init_array(m, 1, 0);
    let s = init_array(m, 1, 0);
    let v = init_array(n, 1, 0);

    for (let col = 0; col < truncation; col++) {
        // fetch z to pass inside ISSV+
        let z = z_all[col];
        incremental_scalable_sign_vector_plus(x, n, m, s, v, z);
        z_all[col] = z;

        let sum_squared = 0;
        for (let j = 0; j < m; j++) {
            let tmp = 0;
            for (let i = 0; i < n; i++) {
                tmp += x[i][j] * z[i];
            }
            c_column[j] = tmp;
            sum_squared += tmp * tmp;
        }

        sum_squared = Math.sqrt(sum_squared);

        for (let j = 0; j < m; j++) {
            r[j][col] = c_column[j] / sum_squared;
        }

        for (let i = 0; i < n; i++) {
            l[i][col] = 0;
            for (let j = 0; j < m; j++) {
                l[i][col] += x[i][j] * r[j][col];
            }
        }

        for (let i = 0; i < n; i++) {
            for (let j = 0; j < m; j++) {
                x[i][j] -= (l[i][col] * r[j][col]);
            }
        }
    }
}


/**
 * Helper function for CD to find the maximizing sign vector using ISSV+ method
 * @param x : array of arrays | matrix being currently decomposed
 * @param n : int | matrix rows
 * @param m : int | matrix cols
 * @param s : array | service vector
 * @param v : array | service vector
 * @param z : array | sign vector to be used as a basis
 * @return void
 */
function incremental_scalable_sign_vector_plus(x, n, m, s, v, z) {
    // determine s
    for (let j = 0; j < m; j++) {
        s[j] = 0;
    }

    for (let i = 0; i < n; i++) {
        for (let j = 0; j < m; j++) {
            s[j] += z[i] * x[i][j];
        }
    }

    // determine v
    for (let i = 0; i < n; i++) {
        let tmp1 = 0;
        let tmp2 = 0;
        for (let j = 0; j < m; j++) {
            tmp1 += z[i] * (x[i][j] * s[j]);
            tmp2 += x[i][j] * x[i][j];
        }
        v[i] = z[i] * (tmp1 - tmp2);
    }

    // find 1st switch pos
    let pos = -1;
    let val = 1E-10;

    for (let i = 0; i < n; i++) {
        if (z[i] * v[i] < 0) {
            if (Math.abs(v[i]) > val) {
                val = Math.abs(v[i]);
                pos = i;
            }
        }
    }

    while (pos !== -1) {
        val = 1E-10;
        pos = -1;

        for (let i = 0; i < n; i++) {
            if (z[i] * v[i] < 0) {
                if (Math.abs(v[i]) > val) {
                    val = Math.abs(v[i]);
                    pos = i;

                    // flip the sign and update V

                    // change sign
                    if (pos !== 0) {
                        z[pos] = z[pos] * (-1);
                    }

                    // calculate the direction of sign flip
                    let factor = z[pos] + z[pos];

                    // update V
                    for (let l = 0; l < n; l++) {
                        if (l !== pos) {
                            // = <x_l, x_pos>
                            let dot_xl_xpos = 0.0;

                            for (let k = 0; k < m; k++) {
                                dot_xl_xpos += x[l][k] * x[pos][k];
                            }

                            v[l] = v[l] + factor * dot_xl_xpos;
                        }
                    }
                }
            }
        }
    }
}


/**
 * Function analog to linear_interpolated_points, except for matrix and only applied in one column (the base series),
 * which is indicated by the base_series_index. See other function for comments (is in retrieve_query.php).
 * @param matrix : array of arrays | a matrix where missing matrix have to interpolated
 * @param base_series_index : int | an index of the series to interpolate
 * @return void
 */
function linear_interpolated_base_series_values(matrix, base_series_index) {
    let rows = matrix.length;
    let mb_start = -1;
    let prev_value = null;
    let step = 0;//init

    for (let i = 0; i < rows; i++) {
        if (matrix[i][base_series_index] === null) {
            // current value is missing - we either start a new block, or we are in the middle of one

            if (mb_start === -1) { // new missing block
                mb_start = i;
                let mb_end = mb_start + 1;

                //lookahead to find the end
                // INDEX IS NEXT NON-null ELEMENT, NOT THE LAST null
                // INCLUDING OUT OF BOUNDS IF THE BLOCK ENDS AT THE END OF TS
                while ((mb_end < rows) && (matrix[mb_end][base_series_index] === null)) {
                    mb_end++;
                }

                let next_value = mb_end === rows ? null : matrix[mb_end][base_series_index];

                if (mb_start === 0) { // special case #1: block starts with array
                    prev_value = next_value;
                }
                if (mb_end === rows) { // special case #2: block ends with array
                    next_value = prev_value;
                }
                step = (next_value - prev_value) / (mb_end - mb_start + 1);
            }
            matrix[i][base_series_index] = prev_value + step * (i - mb_start + 1);
        } else {
            // missing block either ended just new or we're traversing normal data
            prev_value = matrix[i][base_series_index];
            mb_start = -1;
        }
    }
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                       Algebra helper functions                           //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


/**
 * Calculates root of mean square distance between matrices a and b, uses custom divisor as a counter of relevant elements
 * @param a : array of arrays | matrix
 * @param b : array of arrays | matrix
 * @param n : int | divisor for RSME, amount of elements which can be different
 * @return number
 */
function rootmeansquare_distance(a, b, n) {
    let rows = a.length;
    let cols = a[0].length;
    let diff = 0;

    for (let row_index = 0; row_index < rows; row_index++) {
        for (let column_index = 0; column_index < cols; column_index++) {
            diff += Math.pow((a[row_index][column_index] - b[row_index][column_index]), 2);
        }
    }

    return Math.sqrt(diff / n);
}


/**
 * Transposed a matrix
 * @param array : array of arrays | a matrix to transpose
 * @return array : array of arrays | transposed matrix
 */
function trsp(array) {
    let rows = array.length;
    let cols = array[0].length;

    let tp = init_array(cols, rows);

    for (let i = 0; i < rows; i++) {
        for (let j = 0; j < cols; j++) {
            tp[j][i] = array[i][j];
        }
    }

    return tp;
}


/** Performs matrix multiplication M1 * M2
 * @param mat1 : array of arrays | left operand
 * @param mat2 : array of arrays | right operand
 * @return array : array of arrays | new matrix, result of multiplication
 */
function matmult(mat1, mat2) {
    let n1 = mat1.length;
    let m1 = mat1[0].length;
    let n2 = mat2.length;
    let m2 = mat2[0].length;

    if (m1 !== n2) die("Incompatible dimensions of matrices in matmult { n1=n1, m1=m1; n2=n2, m2=m2 } ");

    let res = init_array(n1, m2);

    for (let i = 0; i < n1; i++) {
        for (let j = 0; j < m2; j++) {
            let temp = 0.0;

            for (let k = 0; k < n2; k++) {
                temp += mat1[i][k] * mat2[k][j];
            }

            res[i][j] = temp;
        }
    }

    return res;
}


/** Performs matrix multiplication M1^T * M2
 * @param mat1 : array of arrays | left operand
 * @param mat2 : array of arrays | right operand
 * @return array : array of arrays | new matrix, result of multiplication
 */
function matmult_AT_B(mat1, mat2) {
    let n1 = mat1.length;
    let m1 = mat1[0].length;
    let n2 = mat2.length;
    let m2 = mat2[0].length;

    if (n1 !== n2) die("Incompatible dimensions of matrices in matmult AT_B { n1=n1, m1=m1; n2=n2, m2=m2 } ");

    let res = init_array(m1, m2);

    for (let i = 0; i < m1; i++) {
        for (let j = 0; j < m2; j++) {
            let temp = 0.0;

            for (let k = 0; k < n2; k++) {
                temp += mat1[k][i] * mat2[k][j];
            }

            res[i][j] = temp;
        }
    }

    return res;
}


/** Performs matrix multiplication M1 * M2^T
 * @param mat1 : array of arrays | left operand
 * @param mat2 : array of arrays | right operand
 * @return array : array of arrays | new matrix, result of multiplication
 */
function matmult_A_BT(mat1, mat2) {
    let n1 = mat1.length;
    let m1 = mat1[0].length;
    let n2 = mat2.length;
    let m2 = mat2[0].length;

    if (m1 !== m2) die("Incompatible dimensions of matrices in matmult A_BT { n1=n1, m1=m1; n2=n2, m2=m2 } ");

    let res = init_array(n1, n2);

    for (let i = 0; i < n1; i++) {
        for (let j = 0; j < n2; j++) {
            let temp = 0.0;

            for (let k = 0; k < m2; k++) {
                temp += mat1[i][k] * mat2[j][k];
            }

            res[i][j] = temp;
        }
    }

    return res;
}


/** Put a vector into an empty matrix vertically
 * @param vec : array | vactor to be transformed into a matrix
 * @return array : array of arrays | new matrix, containing an input in the first column
 */
function vecToMatrix(vec) {
    let n = vec.length;

    let res = [];

    for (let i = 0; i < n; i++) {
        res.push([vec[i]]);
    }

    return res;
}

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        Other helper functions                            //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


/**
 * Creates a data structure for the vectors of rows an array filled with null (or other specified value)
 * @param rows : int
 * @param initwith : float [default=null]
 * @return array : array with an empty matrix or null if arguments are invalid
 */
function init_vector(rows, initwith = null) {
    let array = [];
    if (rows === 1) {
        return [initwith];
    } else {
        for (let i = 0; i < rows; i++) {
            array.push(initwith);
        }
    }
    return array;
}


/**
 * Creates a data structure for the matrix of rows x columns as a 2D array filled with null (or other specified value)
 * @param rows : int
 * @param columns : int
 * @param initwith : number [default=null]
 * @return array|null : array of arrays with an empty matrix or null if arguments are invalid
 */
function init_array(rows, columns, initwith = 0) {
    let array = [];
    if (rows === 1) {
        if (columns === 1) {
            return [initwith];
        } else {
            array.push([]);
            for (let column_index = 0; column_index < columns; column_index++) {
                array[0].push(initwith);
            }
        }
    }
    else if (columns === 1) {
        for (let row_index = 0; row_index < rows; row_index++) {
            array.push([initwith]);
        }
    }
    else {
        for (let row_index = 0; row_index < rows; row_index++) {
            let tmp_array = [];
            for (let column_index = 0; column_index < columns; column_index++) {
                tmp_array.push(initwith);
            }
            array.push(tmp_array);
        }
    }
    return array;
}


function matrix_clone(mat) {
    let n = mat.length;
    let m = mat[0].length;

    let newmat = init_array(n, m, 0);

    for (let i = 0; i < n; i++) {
        for (let j = 0; j < m; j++) {
            newmat[i][j] = mat[i][j];
        }
    }

    return newmat;
}

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                       Obsolete helper functions                          //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


/**
 * Calculates frobenius between matrices a and b, the result is the same as ||a - b||_F
 * @param a : array of arrays | matrix
 * @param b : array of arrays | matrix
 * @return float
 */
function frobenius_distance(a, b) {
    let rows = a.length;
    let columns = a[0].length;
    let diff = 0;
    for (let row_index = 0; row_index < rows; row_index++) {
        for (let column_index = 0; column_index < columns; column_index++) {
            diff += pow((a[row_index][column_index] - b[row_index][column_index]), 2);
        }
    }
    return Math.sqrt(diff);
}


function die(message) {
    console.log("[FATAL]");
    console.log(message);
    let THIS_IS_A_MANUALLY_THROWN_CRASH = undefined;
    THIS_IS_A_MANUALLY_THROWN_CRASH();
}


function print_matrix(matrix) {
    let n = matrix.length;
    let m = matrix[0].length;

    for (let i = 0; i < n; i++) {
        let string = "";
        for (let j = 0; j < m; j++) {
            if (matrix[i][j] === null) {
                string += " null\t";
            }
            else {
                string += (matrix[i][j] >= 0 ? " " : "") + matrix[i][j].toFixed(2) + "\t";
            }
        }
        console.log(string);
    }
}

var demo_matx =
    [
        [2, -1, 3],
        [-1, 3, -1],
        [2, -6, -4],
        [-1, 3, 3]
    ];


var demo_recmatx =
    [
        [-12, 8, -4, -8],
        [0, 0, 0, 0],
        [-48, 32, -16, -32],
        [null, 64, -32, -64],
        [null, 24, -12, -24],
        [null, 64, -32, -64],
        [null, 16, -8, -16],
        [null, 8, -4, -8],
        [null, -32, 16, 32],
        [null, 8, -4, -8],
        [12, -8, 4, 8],
        [null, -24, 12, 24],
        [null, 16, -8, -16],
        [-12, 8, -4, -8],
        [24, -16, 8, 16]
    ];


// cheat - function that uses all others
function testall() {
    RMV();
    RMV_all();
    rootmeansquare_distance();
    vecToMatrix();
    frobenius_distance();
    matmult_AT_B();
    init_vector();
    matmult();
}