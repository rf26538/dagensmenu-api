<?php 

namespace App\Helpers;

use App\Shared\EatCommon\Language\TranslatorFactory;

class Translate 
{
    private static $translatorFactory;

    public static function msg(string $message): string
    {
        
        if (!self::$translatorFactory)
        { 
            self::$translatorFactory = TranslatorFactory::getTranslator();
        }
        
        return self::$translatorFactory->translate($message);
    }
}
?>