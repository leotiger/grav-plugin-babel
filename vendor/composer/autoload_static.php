<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit26465588313a16d1ead552a8067117e0
{
    public static $files = array (
        '290dd4ba42f11019134caca05dbefe3f' => __DIR__ . '/..' . '/teamtnt/tntsearch/helper/helpers.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'TeamTNT\\TNTSearch\\' => 18,
        ),
        'G' => 
        array (
            'Grav\\Plugin\\Babel\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TeamTNT\\TNTSearch\\' => 
        array (
            0 => __DIR__ . '/..' . '/teamtnt/tntsearch/src',
        ),
        'Grav\\Plugin\\Babel\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit26465588313a16d1ead552a8067117e0::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit26465588313a16d1ead552a8067117e0::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
