<?php
if ($argc < 2) {
    die("Usage: php generate_pdf.php <latex_file_path>\n");
}

$latex_file = $argv[1];
if (!file_exists($latex_file)) {
    die("LaTeX file not found: $latex_file\n");
}

// Compile with latexmk (assumes TeX Live is installed)
exec("latexmk -pdf -interaction=nonstopmode $latex_file 2>&1", $output, $return_var);

if ($return_var !== 0) {
    echo "Error compiling LaTeX: " . implode("\n", $output) . "\n";
    exit(1);
}
?>