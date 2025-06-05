<?php
// Load the ABI file into a variable (not a constant)
$contractABI = file_get_contents('./js/contract-abi.json');

// Check if the file was loaded successfully
if ($contractABI === false) {
    die("Error: Unable to load contract ABI file. Please check if './js/contract-abi.json' exists and is readable.");
}

// Output the ABI for testing
echo "Contract ABI:\n";
echo $contractABI . "\n";
?>