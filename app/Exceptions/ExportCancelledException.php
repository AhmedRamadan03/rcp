<?php

namespace App\Exceptions;

class ExportCancelledException extends \RuntimeException
{
    // exception بسيطة لوقف الـ chunk loop بشكل نظيف
}