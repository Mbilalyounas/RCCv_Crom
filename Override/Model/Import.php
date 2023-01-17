<?php

namespace RCCsv\Cron\Override\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Adapter\FileTransferFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Math\Random;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\ImportExport\Helper\Data as DataHelper;
use Magento\ImportExport\Model\Export\Adapter\CsvFactory;
use Magento\ImportExport\Model\History;
use Magento\ImportExport\Model\Import\ConfigInterface;
use Magento\ImportExport\Model\Import\Entity\Factory;
use Magento\ImportExport\Model\ResourceModel\Import\Data;
use Magento\ImportExport\Model\Source\Import\Behavior\Factory as BehaviorFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Psr\Log\LoggerInterface;

class Import extends \Magento\ImportExport\Model\Import
{
    /**
     * @var History
     */
    private $importHistoryModel;
    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    public function __construct(
        LoggerInterface $logger,
        Filesystem $filesystem,
        DataHelper $importExportData,
        ScopeConfigInterface $coreConfig,
        ConfigInterface $importConfig,
        Factory $entityFactory,
        Data $importData,
        CsvFactory $csvFactory,
        FileTransferFactory $httpFactory,
        UploaderFactory $uploaderFactory,
        BehaviorFactory $behaviorFactory,
        IndexerRegistry $indexerRegistry,
        History $importHistoryModel,
        DateTime $localeDate,
        DataPersistorInterface $dataPersistor,
        array $data = [],
        ManagerInterface $messageManager = null,
        Random $random = null
    ) {
        $this->importHistoryModel = $importHistoryModel;
        parent::__construct($logger, $filesystem, $importExportData, $coreConfig, $importConfig, $entityFactory, $importData, $csvFactory, $httpFactory, $uploaderFactory, $behaviorFactory, $indexerRegistry, $importHistoryModel, $localeDate, $data, $messageManager, $random);
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * Import source file structure to DB.
     *
     * @return bool
     * @throws LocalizedException
     */
    public function importSource()
    {
        if ($this->dataPersistor->get('is_custom_import')) {
            $this->setData('entity', 'catalog_product');
            $this->setData('behavior', 'append');
        } else {
            $this->setData('entity', $this->getDataSourceModel()->getEntityTypeCode());
            $this->setData('behavior', $this->getDataSourceModel()->getBehavior());
        }
        //Validating images temporary directory path if the constraint has been provided
        if ($this->hasData('images_base_directory')
            && $this->getData('images_base_directory') instanceof Filesystem\Directory\ReadInterface
        ) {
            /** @var Filesystem\Directory\ReadInterface $imagesDirectory */
            $imagesDirectory = $this->getData('images_base_directory');
            if (!$imagesDirectory->isReadable()) {
                $rootWrite = $this->_filesystem->getDirectoryWrite(DirectoryList::ROOT);
                $rootWrite->create($imagesDirectory->getAbsolutePath());
            }
            try {
                $this->setData(
                    self::FIELD_NAME_IMG_FILE_DIR,
                    $imagesDirectory->getAbsolutePath($this->getData(self::FIELD_NAME_IMG_FILE_DIR))
                );
                $this->_getEntityAdapter()->setParameters($this->getData());
            } catch (ValidatorException $exception) {
                throw new LocalizedException(__('Images file directory is outside required directory'), $exception);
            }
        }

        $this->importHistoryModel->updateReport($this);
        $this->addLogComment(__('Begin import of "%1" with "%2" behavior', $this->getEntity(), $this->getBehavior()));

        $result = $this->processImport();

        if ($result) {
            $this->addLogComment(
                [
                    __(
                        'Checked rows: %1, checked entities: %2, invalid rows: %3, total errors: %4',
                        $this->getProcessedRowsCount(),
                        $this->getProcessedEntitiesCount(),
                        $this->getErrorAggregator()->getInvalidRowsCount(),
                        $this->getErrorAggregator()->getErrorsCount()
                    ),
                    __('The import was successful.'),
                ]
            );
            $this->importHistoryModel->updateReport($this, true);
        } else {
            $this->importHistoryModel->invalidateReport($this);
        }

        return $result;
    }
}
