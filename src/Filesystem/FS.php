<?php
namespace App\Filesystem;


class FS extends \Symfony\Component\Filesystem\Filesystem
{
    public static function cleanse($filename)
    {
        return preg_replace('~[/\:;=*?"<>|]+~si', '', $filename);
    }
    
    /**
     * Соединить несколько частей пути в один
     * @param array ...$array
     * @return string
     */
    public static function conc(...$array)
    {
        return implode(DIRECTORY_SEPARATOR, $array);
    }
    
    /**
     * Заменить последний элемент пути и вернуть результат
     * @param $originalPath
     * @param $endName
     * @return string
     * @throws \Exception
     */
    public static function replace($originalPath, $endName)
    {
        $files = preg_split('~[/\\\\]~si', $originalPath);
        if (empty($files)) {
            throw new \Exception('Получен неверный путь');
        }
        array_pop($files);
        $files[] = $endName;
        
        return implode(DIRECTORY_SEPARATOR, $files);
    }
    
    public static function lastname($path)
    {
        $files = preg_split('~[/\\\\]~si', $path);
        if (empty($files)) {
            throw new \Exception('Получен неверный путь');
        }
        return array_pop($files);
    }
}