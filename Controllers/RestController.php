<?php

namespace Sphere\Api\Controllers;

use Exception;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Sphere\Api\Helpers\WithMedia;
use Sphere\Api\Model;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Validator;
use Illuminate\Http\Request;
use Sphere\Api\Utils\RequestProcessor;
use Sphere\Api\Error;

class RestController extends Controller
{

    protected $defaultOptions = [
        // Classes
        'model' => null,
        'resource' => Resource::class,
        'policy' => false,
        // 'validator' => null,

        // Defaults
        'defaultSorting' => null,
        'defaultRelations' => null,
        'defaultScopes' => null,
        'defaultLimit' => 100,

        // Allowed
        'relations' => [],
        'scopes' => [],
        'filters' => ['id'],

        // Limit
        // 'limit' => 1000,
        // 'maxLimit' => 1000,
    ];

    /**************************************************************************
        System
    **************************************************************************/

    public function boot()
    {
        parent::boot();
        $this->loadResourceOptions();
    }

    protected function loadResourceOptions()
    {
        $options = app('Sphere\Api\Router')->getCurrentResourceOptions();

        foreach ($this->options as $key => $value) {
            if (!empty($options->$key)) {
                $this->options[$key] = $options->$key;
            }
        }

        if (!$this->options('model')) {
            throw new Exception('Controller model option not set in ' . get_called_class(), 1);
        }
    }

    /**************************************************************************
        REST Methods
    **************************************************************************/

    /**
     * Список записей
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $model = $this->model();

        $this->processor->applyRelations($model);
        $this->processor->applyScopes($model);
        $this->processor->applySorting($model);
        $this->processor->applyFilters($model);

        $paginator = $this->processor->makePaginator($model);

        return $this->response->collection($paginator);
    }

    /**
     * Выбор записи по id
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function read($id)
    {
        $model = $this->model();

        $this->processor->applyRelations($model);
        // $this->processor->applyScopes($model);
        // $this->processor->applyFilters($model);

        $record = $model->findOrFail($id);

        return $this->response->item($record);
    }

    /**
     * Создание новой записи
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create()
    {
        $record = $this->makeModel();

        if (!($record instanceof Model)) {
            return $record;
        }

        $record->save();

        return $this->read($record->id);
    }

    /**
     * @return \Sphere\Api\Model|array
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function makeModel()
    {
        $data = $this->getRequestContent();

        if (!$data) {
            return $this->response->error(Error::REST_CREATE_EMPTY_DATA);
        }

        /** @var \Sphere\Api\Model $record */
        $record = $this->model($data);

        $rules = $record->rules();
        if ($rules) {
            $this->validate($this->request, $rules);
        }

        return $record;
    }

    /**
     * Обновление записи
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update($id)
    {
        $record = $this->model()->findOrFail($id);

        if ($this->options['policy']) {
            $this->authorize('update', $record);
        }

        if ($data = $this->getRequestContent()) {
            $record->fill($data);

            // Проверяем только поля которые были изменены (отправлены)
            $rules = $record->rules();
            if ($rules) {
                $rules = array_only($rules, array_keys($data));
                $this->validate($this->request, $rules);
            }

            $record->save();
        }

        return $this->read($id);
    }

    /**
     * Удаление записи
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function delete($id)
    {
        $record = $this->model()->findOrFail($id);

        if ($this->options['policy']) {
            $this->authorize('delete', $record);
        }

        if ($record->delete()) {
            return $this->response->success('');
        }

        return $this->response->error(Error::REST_DELETE_RECORD_ERROR);
    }


    /**************************************************************************
        Helpers
    **************************************************************************/

    /**
     * Создаем экземпляр модели
     * @param  array  $attributes атрибуты модели
     * @return Illuminate\Database\Eloquent\Model
     */
    public function model($attributes = [])
    {
        $modelClass = $this->options('model');

        return new $modelClass($attributes);
    }

    protected function getRequestContent()
    {
        if ($this->request->isJson()) {
            $content = $this->request->getContent();

            if ($content) {
                return json_decode($content, true);
            }
        } elseif ($this->request->files->count() > 0) {
            $data = $this->request->input();

            try {
                foreach ($this->request->files->keys() as $key) {
                    $file = $this->request->file($key);

                    $data[$key] = $this->uploadFile($file, $key);
                }
            } catch (InvalidArgumentException | FileException $e) {
                return [];
            }

            return $data;
        }

        return [];
    }

    /**
     * Перемещает загруженный файл в папку с файлами
     *
     * @param UploadedFile $file
     * @param string       $key В каком поле будет храниться путь к файлу
     * @return string Url path файла
     */
    protected function uploadFile(UploadedFile $file, $key)
    {
        $modelClass = $this->options('model');

        if (!is_a($modelClass, WithMedia::class, true)) {
            throw new InvalidArgumentException('model dont implement WithMedia interface');
        }

        $filePath = $modelClass::uploadPath($key);

        $filename = $file->hashName();

        $file->move(storage_path("app/{$filePath}"), $filename);

        return "{$filePath}/{$filename}";
    }

    protected function initProcessor()
    {
        $processor = new RequestProcessor();

        $processor->setDefaults([
            'relations' => $this->options('defaultRelations'),
            'scopes'    => $this->options('defaultScopes'),
            'sorting'   => $this->options('defaultSorting'),
            'limit'     => $this->options('defaultLimit'),
        ]);

        $processor->setAllowed([
            'relations' => $this->options('relations'),
            'scopes'    => $this->options('scopes'),
            'filters'   => $this->options('filters'),
        ]);

        $processor->parseRequest($this->request);

        return $processor;
    }
}
