<?php

namespace RCCsv\Cron\Model\Import\ErrorProcessing;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorFactory;

class ProcessingErrorAggregator extends \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregator
{
    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    public function __construct(
        ProcessingErrorFactory $errorFactory,
        DataPersistorInterface $dataPersistor
    ){
        parent::__construct($errorFactory);
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * Check if import has to be terminated
     *
     * @return bool
     */
    public function hasToBeTerminated()
    {
        if ($this->dataPersistor->get('is_custom_import')) {
            return false;
        } else {
            return $this->hasFatalExceptions() || $this->isErrorLimitExceeded();
        }
    }
}
