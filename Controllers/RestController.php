<?php

namespace Sphere\Api\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Builder;
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
        $query = $this->model()->query();

        $this->processor->applyRelations($query);
        $this->processor->applyScopes($query);
        $this->processor->applySorting($query);
        $this->processor->applyFilters($query);

        $query = $this->fetching($query);

        $paginator = $this->processor->makePaginator($query);

        return $this->response->collection($paginator);
    }

    /**
     * Вызывается перед получением списка записей
     *
     * @param Builder $query
     * @return Builder
     */
    public function fetching(Builder $query)
    {
        return $query;
    }

    /**
     * Выбор записи по id
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function read($id)
    {
        $query = $this->model()->query();

        $this->processor->applyRelations($query);
        // $this->processor->applyScopes($model);
        // $this->processor->applyFilters($model);

        $query = $this->reading($query);

        $record = $query->findOrFail($id);

        $record = $this->readed($record);

        return $this->response->item($record);
    }

    /**
     * Вызывается перед получением записи
     *
     * @param Builder $query
     * @return Builder
     */
    public function reading(Builder $query)
    {
        return $query;
    }

    /**
     * Вызывается после получения записи
     * Если удастся придумать название метода получше - будет здорово
     *
     * @param Model $record
     * @return Model
     */
    public function readed(Model $record)
    {
        return $record;
    }

    /**
     * Создание новой записи
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create()
    {
        $model = $this->makeModel();

        if (!($model instanceof Model)) {
            return $model;
        }

        $model = $this->creating($model);

        // Из creating можно вернуть ошибку, и мы вернем ее пользователю
        if (is_array($model)) {
            return $model;
        }

        $model->save();

        $model = $this->created($model);

        return $this->response->item($model);
    }

    /**
     * Вызывается перед созданием записи
     *
     * @param Model $model
     * @return Model
     */
    public function creating(Model $model)
    {
        return $model;
    }

    /**
     * Вызывается после создания записи
     *
     * @param Model $model
     * @return Model
     */
    public function created(Model $model)
    {
        return $model;
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

            $record = $this->updating($record, $data);

            // Из updating можно вернуть ошибку, и мы вернем ее пользователю
            if (is_array($record)) {
                return $record;
            }

            $record->save();

            $record = $this->updated($record);
        }

        return $this->response->item($record);
    }

    /**
     * Вызывается перед обновлением записи
     *
     * @param Model $record
     * @param array $data
     * @return Model
     */
    public function updating(Model $record, $data)
    {
        return $record;
    }

    /**
     * Вызывается после обновления записи
     *
     * @param Model $record
     * @return Model
     */
    public function updated(Model $record)
    {
        return $record;
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

        $record = $this->deleting($record);

        if ($record->delete()) {
            $record = $this->deleted($record);

            return $this->response->success('');
        }

        return $this->response->error(Error::REST_DELETE_RECORD_ERROR);
    }

    /**
     * Вызывается перед удалением записи
     *
     * @param Model $record
     * @return Model
     */
    public function deleting(Model $record)
    {
        return $record;
    }

    /**
     * Вызывается после удаления записи
     *
     * @param Model $record
     * @return Model
     */
    public function deleted(Model $record)
    {
        return $record;
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
