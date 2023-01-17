<?php
namespace RCCsv\Cron\Model;

use Magento\ImportExport\Model\Import as MagentoImport;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class Import
 * @package RCCsv\Cron\Model
 */
class Import
{
    /**
     * @var \Magento\ImportExport\Model\Import
     */
    private $importModel;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $readFactory;

    /**
     * @var \Magento\ImportExport\Model\Import\Source\CsvFactory
     */
    private $csvSourceFactory;

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexerCollectionFactory;

    /**
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\ImportExport\Model\Import $importModel
     * @param \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory
     * @param \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
     */
    public function __construct(
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\ImportExport\Model\Import $importModel,
        \Magento\ImportExport\Model\Import\Source\CsvFactory $csvSourceFactory,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
    ) {
         $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/RainCCliImportModel.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('RC Cron Model');

        $this->eavConfig = $eavConfig;
        $this->csvSourceFactory = $csvSourceFactory;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->readFactory = $readFactory;
         $logger->info('setData'.print_r([
                 "entity"                    => "catalog_product",
        "behavior"                  => "append",
        "validation_strategy"       => "validation-skip-errors",
        "allowed_error_count"       => 1000,
        "_import_field_separator"   => ",",
        "_import_multiple_value_separator" => ",",
        "import_images_file_dir"    => "pub/media/catalog/product"
            ], true));
        $importModel->setData(
           [ "entity"                    => "catalog_product",
        "behavior"                  => "append",
        "validation_strategy"       => "validation-skip-errors",
        "allowed_error_count"       => 1000,
        "_import_field_separator"   => ",",
        "_import_multiple_value_separator" => ",",
        "import_images_file_dir"    => "pub/media/catalog/product"
    ]
        );

        $this->importModel = $importModel;
    }

    /**
     * @param $filePath Absolute file path to CSV file
     */
    public function setFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException();
        }

        $pathInfo = pathinfo($filePath);
        $validate = $this->importModel->validateSource($this->csvSourceFactory->create(
            [
                'file' => $pathInfo['basename'],
                'directory' => $this->readFactory->create($pathInfo['dirname'])
            ]
        ));
        // return $validate;
        if (!$validate) {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @param $imagesPath
     */
    public function setImagesPath($imagesPath)
    {
        $this->importModel->setData(MagentoImport::FIELD_NAME_IMG_FILE_DIR, $imagesPath);
    }

    /**
     * @param $behavior
     */
    public function setBehavior()
    {
          $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/RainCCliImportModel.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('setBehaviorallowed_error_count');
        $this->importModel->setData("validation_strategy", "validation-skip-errors");
        $this->importModel->setData("allowed_error_count", "100000");
        $this->importModel->setData('behavior', MagentoImport::BEHAVIOR_APPEND);
    }


	public function getData($data)
    {
        return $this->importModel->getData($data);
    }

    public function setData($data)
    {
        return $this->importModel->setData($data);
    }
    /**
     * @return bool
     */
    public function execute()
    {
        $result = $this->importModel->importSource();
        $errorAggregator = $this->importModel->getErrorAggregator();
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/RainCCliImport.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        foreach ($this->getErrorMessages($errorAggregator) as $errorMessage) {
            $logger->info($errorMessage);
        }
        if ($result) {
            $this->importModel->invalidateIndex();
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getFormattedLogTrace()
    {
        return $this->importModel->getFormatedLogTrace();
    }

    /**
     * @return MagentoImport\ErrorProcessing\ProcessingError[]
     */
    public function getErrors()
    {
        return $this->importModel->getErrorAggregator()->getAllErrors();
    }

    /**
     * Get all Error Messages from Import Results
     *
     * @param \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator
     * @return array
     */
    private function getErrorMessages(ProcessingErrorAggregatorInterface $errorAggregator)
    {
        $messages = [];
        $rowMessages = $errorAggregator->getRowsGroupedByErrorCode([], [AbstractEntity::ERROR_CODE_SYSTEM_EXCEPTION]);
        foreach ($rowMessages as $errorCode => $rows) {
            $messages[] = $errorCode . ' ' . __('in row(s):') . ' ' . implode(', ', $rows);
        }
        return $messages;
    }
}

