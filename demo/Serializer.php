<?php

namespace rest\components\api;

use yii\rest\Serializer as BaseSerializer;
use rest\components\validation\ErrorMessage;
use rest\components\validation\ErrorListInterface;
use yii\base\Arrayable;
use yii\base\Model;
use yii\data\DataProviderInterface;

/**
 * Class Serializer
 */
class Serializer extends BaseSerializer
{
    /**
     * @inheritdoc
     */
    public $collectionEnvelope = 'result';

    /**
     * @var bool Add pagination headers to response
     */
    public $addPaginationHeaders = false;

    /**
     * @inheritdoc
     */
    public function serialize($data)
    {
        if ($data instanceof Model && $data->hasErrors()) {
            $serializedData = $this->serializeModelErrors($data);
        } elseif ($data instanceof Arrayable) {
            $serializedData = $this->serializeModel($data);
        } elseif ($data instanceof DataProviderInterface) {
            $serializedData = $this->serializeDataProvider($data);
        } else {
            $serializedData = ['result' => $data];
        }
        return array_merge(
            [
                'code' => $this->response->getStatusCode(),
                'status' => $this->response->getIsSuccessful() ? 'success' : 'error',
            ],
            $serializedData
        );
    }

    /**
     * @inheritdoc
     */
    protected function serializeModelErrors($model)
    {
        $this->response->setStatusCode(422, 'Data Validation Failed.');
        $result = [];
        foreach ($model->getFirstErrors() as $attribute => $message) {
            if ($message instanceof ErrorMessage) {
                $code = $message->getCode();
                $params = $message->getParams();
            } else {
                $code = ErrorListInterface::ERR_BASIC;
                $params = [];
            }
            $serializedParams = [];
            foreach ($params as $name => $value) {
                $serializedParams[] = [
                    'name' => $name,
                    'value' => (string) $value,
                ];
            }
            $result[] = [
                'field' => $attribute,
                'message' => (string) $message,
                'code' => $code,
                'params' => $serializedParams
            ];
        }
        return ['result' => $result];
    }

    /**
     * @inheritdoc
     */
    protected function serializeDataProvider($dataProvider)
    {
        if ($this->preserveKeys) {
            $models = $dataProvider->getModels();
        } else {
            $models = array_values($dataProvider->getModels());
        }
        $models = $this->serializeModels($models);
        $pagination = $dataProvider->getPagination();

        if ($pagination !== false && $this->addPaginationHeaders) {
            $this->addPaginationHeaders($pagination);
        }

        if ($this->request->getIsHead()) {
            return null;
        } elseif (!$this->collectionEnvelope) {
            return $models;
        }

        $result = [
            $this->collectionEnvelope => $models,
        ];
        if ($pagination !== false) {
            $serializedPagination = $this->serializePagination($pagination);
            $result[$this->metaEnvelope]['pagination'] = $serializedPagination[$this->metaEnvelope];
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function serializeModel($model)
    {
        return ['result' => parent::serializeModel($model)];
    }
}
