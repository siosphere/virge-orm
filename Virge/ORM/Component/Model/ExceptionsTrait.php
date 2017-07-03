<?php
namespace Virge\ORM\Component\Model;

use Virge\ORM\Exception\{
    DeleteModelException,
    LoadModelException,
    SaveModelException
};

/**
 * Add to a model to throw exceptions when model interactions fail
 * i.e. Model fails to save or load
 */
trait ExceptionsTrait
{
    protected function _handleError($errorType, $errorMessage)
    {
        switch($errorType) {
            case self::ERR_DELETE:
                throw new DeleteModelException($errorMessage);
            case self::ERR_LOAD:
                throw new LoadModelException($errorMessage);
            case self::ERR_SAVE:
                throw new SaveModelException($errorMessage);
        }
    }
}