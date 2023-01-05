<?php

namespace App\CustomProviders\Interfaces;

interface IHaveHowToImplement
{
    public static function getHowToImplementAction($action): ?string;
}
