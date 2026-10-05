<?php

namespace App\ThirdParty\IVR;

interface IvrInterface
{
    public function isSuccessful($response): bool;

    public function call($request);
}
