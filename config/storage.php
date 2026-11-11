<?php

$configuredStorageDirectory = getenv('INVOICE_STORAGE_PATH');
define(
    'INVOICE_STORAGE_DIRECTORY',
    is_string($configuredStorageDirectory) && $configuredStorageDirectory !== ''
        ? $configuredStorageDirectory
        : dirname(__DIR__) . '/storage/facturas_sap'
);
