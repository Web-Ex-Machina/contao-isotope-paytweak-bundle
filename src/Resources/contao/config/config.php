<?php

declare(strict_types=1);

/*
 * Add Paytweak error codes
 */
$GLOBALS['PAYTWEAK_API_ERROR_CODES'] = [
    '401' => 'LIMIT EXEEDED',
    '402' => 'MISSING HEADER',
    '403' => 'AUTH FAILURE',
    '404' => 'NOT FOUND',
    '405' => 'MISSING PARAMETER',
    '406' => 'BAD SYNTAX',
    '407' => 'OPERATION FAILED',
    '408' => 'TIME OUT',
    '409' => 'CONFLICT',
    '410' => 'NO CREDIT',
];

/*
 * Register Paytweak payment
 */
\Isotope\Model\Payment::registerModelType('paytweak', \ContaoIsotopePaytweakBundle\Model\Payment\Paytweak::class);
