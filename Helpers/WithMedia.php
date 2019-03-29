<?php

namespace Sphere\Api\Helpers;

use InvalidArgumentException;

/**
 * У модели есть файлы
 */
interface WithMedia
{
    /**
     * Возвращает путь к папке, в которой будут храниться файлы модели
     *
     * @param string $field В каком поле будет храниться путь к файлу
     * @return string
     * @throws InvalidArgumentException Если мы не знаем такого поля в модели для хранения файлов
     */
    public static function uploadPath($field);
}
