<?php

namespace MLocati\IMAP;

use Exception as PHPException;

class Exception extends PHPException
{
    /**
     * @param string|null $message
     * @param int|null $code
     * @param unknown $previous
     */
    public function __construct($message = null, $code = null, $previous = null)
    {
        $message = (string) $message;
        $errors = function_exists('imap_errors') ? @imap_errors() : false;
        if (!empty($errors)) {
            $errors = implode("\n", $errors);
            if ($errors !== '') {
                $message .= (($message === '') ? '' : "\n").$errors;
            }
        }
        $alerts = function_exists('imap_alerts') ? @imap_alerts() : false;
        if (!empty($alerts)) {
            $alerts = implode("\n", $alerts);
            if ($alerts !== '') {
                $message .= (($message === '') ? '' : "\n").$alerts;
            }
        }
        parent::__construct($message, $code, $previous);
    }
}
