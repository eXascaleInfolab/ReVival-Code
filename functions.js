//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                 MATRIX ARITHMETICS AND RELATED FUNCTIONS                 //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

// Matrix multiplication
function mult(a, b) {
    let a_rows = a.length,
        a_cols = a[0].length,
        b_rows = b.length,
        b_cols = b[0].length;

    if (a_cols !== b_rows) {
        $('#here').append("Multiplication error: a_cols != b rows");
    }
    let prod = new Array(a_rows);
    for (let i = 0; i < a_rows; i++) {
        prod[i] = new Array(b_cols);
    }
    for (let i = 0; i < a_rows; i++) {
        for (let j = 0; j < b_cols; j++) {
            prod[i][j] = 0;
            for (let y = 0; y < a_cols; y++) {
                prod[i][j] += a[i][y] * b[y][j];
            }
        }
    }
    return prod;
}

// Matrix addition
function add(a, b) {
    let a_rows = a.length,
        a_cols = a[0].length,
        b_rows = b.length,
        b_cols = b[0].length;


    if (a_rows !== b_rows) $('#here').append("Addition error: a_rows !=  b_rows");
    if (a_cols !== b_cols) $('#here').append("Addition error: a_cols !=  b_cols");

    let sum = init_array(a_rows, a_cols);

    for (let i = 0; i < a_rows; i++) {
        for (let j = 0; j < a_cols; j++) {
            sum[i][j] = a[i][j] + b[i][j];
        }
    }
    return sum;
}

// Matrix subtraction
function sub(a, b) {
    let a_rows = a.length,
        a_cols = a[0].length,
        b_rows = b.length,
        b_cols = b[0].length;

    if (a_rows !== b_rows) $('#here').append("Subtraction error: a_rows !=  b_rows");
    if (a_cols !== b_cols) $('#here').append("Subtraction error: a_cols !=  b_cols");

    let diff = init_array(a_rows, a_cols);
    for (let i = 0; i < a_rows; i++) {
        for (let j = 0; j < a_cols; j++) {
            diff[i][j] = a[i][j] - b[i][j];
        }
    }
    return diff;
}

// Scalar division for matrices
function scalar_div(array, scalar) {
    let rows = array.length;
    let cols = array[0].length;
    let div = [];
    for (let i = 0; i < rows; i++) {
        let tmp = [];
        for (let j = 0; j < cols; j++) {
            if (scalar !== 0) {
                tmp.push(array[i][j] / scalar);
            }
            else {
                tmp.push(0);
            }
        }
        div.push(tmp);
    }
    return div;
}

// Scalar multiplication for matrices
function scalar_mult(array, scalar) {
    let rows = array.length;
    let cols = array[0].length;

    let prod = [];

    for (let i = 0; i < rows; i++) {
        let tmp = [];
        for (let j = 0; j < cols; j++) {
            tmp.push(array[i][j] * scalar);
        }
        prod.push(tmp);
    }
    return prod;
}

// Matrix transposition
function transpose(array) {
    let rows = array.length,
        cols = array[0].length;

    let tp = [];
    for (let i = 0; i < cols; i++) {
        let tmp_array = [];
        for (let j = 0; j < rows; j++) {
            tmp_array.push(array[j][i]);
        }
        tp.push(tmp_array);
    }
    return tp;
}

// Matrix row appendition
function row_append(a, b) {
    let a_rows = a.length,
        a_cols = a[0].length,
        b_rows = b.length,
        b_cols = b[0].length;

    if (a_cols !== b_cols) $('#here').append("Row appendition error: a_cols != b_cols");

    let result = init_array((a_rows + b_rows), a_cols);
    for (let i = 0; i < (a_rows + b_rows); i++) {
        if (i < a_rows) {
            for (let j = 0; j < a_cols; j++) {
                result[i][j] = a[i][j];
            }
        }
        else {
            for (let j = 0; j < a_cols; j++) {
                result[i][j] = b[(i - a_rows)][j];
            }
        }
    }
    return result;
}

// Matrix column appention
function col_append(a, b) {
    let a_rows = a.length,
        a_cols = a[0].length,
        b_rows = b.length,
        b_cols = b[0].length;

    if (a_rows !== b_rows) $('#here').append("Column appention error: a_rows != b_rows");

    let result = init_array(a_rows, (a_cols + b_cols));
    for (let i = 0; i < (a_cols + b_cols); i++) {
        if (i < a_cols) {
            for (let j = 0; j < a_rows; j++) {
                result[j][i] = a[j][i];
            }
        }
        else {
            for (let j = 0; j < a_rows; j++) {
                result[j][i] = b[j][(i - a_cols)];
            }
        }
    }
    return result;
}

// Dynamic Matrix row appendition. Appends a row of element to the matrix
function dyn_row_append(a, element) {
    let a_rows = a.length,
        a_cols = a[0].length;

    let result = init_array((a_rows + 1), a_cols);
    for (let i = 0; i < (a_rows + 1); i++) {
        if (i === a_rows) {
            for (let j = 0; j < a_cols; j++) {
                result[i][j] = element;
            }
        }
        else {
            for (let j = 0; j < a_cols; j++) {
                result[i][j] = a[i][j];
            }
        }
    }
    return result;
}

// Dynamic Matrix row prepention. Prepends a row of element to the matrix
function dyn_row_prepend(a, element) {
    let a_rows = a.length,
        a_cols = a[0].length;

    let result = new Array((a_rows + 1));
    for (let i = 0; i < (a_rows + 1); i++) {
        result[i] = new Array(a_cols);
        if (i === 0) {
            for (let j = 0; j < a_cols; j++) {
                result[i][j] = element;
            }
        }
        else {
            for (let j = 0; j < a_cols; j++) {
                result[i][j] = a[i - 1][j];
            }
        }
    }
    return result;
}

// Dynamic Matrix column appention. Appends colum of element to the matrix
function dyn_col_append(a, element) {
    let a_rows = a.length,
        a_cols = a[0].length;

    let result = init_array(a_rows, (a_cols + 1));
    for (let i = 0; i < (a_cols + 1); i++) {
        if (i === a_cols) {
            for (let j = 0; j < a_rows; j++) {
                result[j][i] = element;
            }
        }
        else {
            for (let j = 0; j < a_rows; j++) {
                result[j][i] = a[j][i];
            }
        }
    }
    return result;
}


function rows(matrix, start, end) {
    let new_matrix = [];
    for (let i = start; i < end; i++) {
        new_matrix.push(matrix[i]);
    }
    return new_matrix;
}

function columns(matrix, start, end) {
    let rows = matrix.length;
    let new_matrix = [];
    for (let i = 0; i < rows; i++) {
        let tmp = [];
        for (let j = start; j < end; j++) {
            tmp.push(matrix[i][j]);
        }
        new_matrix.push(tmp);
    }
    return new_matrix;
}


function extract_row(a, index) {
    let row = a[index];
    let matrix = [];
    matrix.push(row);
    return matrix;
}

function column(a, index) {
    let a_rows = a.length;
    let matrix = [];
    for (let i = 0; i < a_rows; i++) {
        let tmp = [];
        tmp.push(a[i][index]);
        matrix.push(tmp);
    }
    return matrix;
}

function get_value(matrix) {
    return matrix[0][0];
}


// Euclidean norm of a vector
function euclid_norm(matrix) {
    let rows = matrix.length,
        cols = matrix[0].length;
    let norm = 0;

    if (cols === 1) { //it's a column vector
        for (let i = 0; i < rows; i++) {
            norm += Math.pow(matrix[i][0], 2);
        }
    }
    else { //it's a row vector
        for (let i = 0; i < cols; i++) {
            norm += Math.pow(matrix[0][i], 2);
        }
    }

    return Math.sqrt(norm);
}

function norm(vector) {
    let length = vector[0].length;
    let tmp = 0;
    for (let i = 0; i < length; i++) {
        tmp += vector[0][i];
    }
    return Math.sqrt(tmp);
}

function clone_array(array) {
    let rows = array.length;

    let clone = [];

    for (let i = 0; i < rows; i++) {
        clone.push(array[i].slice(0));
    }

    return clone;
}

// Helper function that initializes and returns an array of arrays (that represents a matrix) of given size with null values
function init_array(rows, columns) {
    let array = [];
    for (let row_index = 0; row_index < rows; row_index++) {
        let tmp_array = [];
        for (let column_index = 0; column_index < columns; column_index++) {
            tmp_array.push(null);
        }
        array.push(tmp_array);
    }
    return array;
}