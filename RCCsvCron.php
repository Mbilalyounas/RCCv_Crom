<?php

namespace RCCsv\Cron;

use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Class RCCsvCron
 *
 * @package RCCsv\Cron
 */
class RCCsvCron
{
	 protected $logger;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;
    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;
    /**
     * @var Pool
     */
    private $cacheFrontendPool;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        DataPersistorInterface $dataPersistor,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\State $state,
		LoggerInterface $logger,
        \Magento\Framework\File\Csv $csvProcessor
    ) {
        $this->objectManager = $objectManager;
        $this->state = $state;
		$this->logger = $logger;
        $this->csvProcessor = $csvProcessor;
        $this->dataPersistor = $dataPersistor;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }


    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $isActive = $this->objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('RCCsv/RCCsv/active');
        if (!$isActive) {
            return $this;
        }
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/RainCCliImport.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('RC Cron Started');

		//$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager


		$cron_file_path = $this->objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('RCCsv/RCCsv/file_path');

		$directory = $this->objectManager->get('\Magento\Framework\Filesystem\DirectoryList');

		$rootPath  =  $directory->getRoot();

		$this->logger->info('Cron Works');


		$import = $this->getImportModel();

        $logger->info('Validation Strategy '.$import->getData('validation_strategy'));

        $logger->info('Import Model Called');
		$file = $rootPath.'/'.$cron_file_path;

        //$file = $rootPath.'/var/importexport/catalog_product.csv';
		$behavior = "append";

		$import->setImagesPath('');
        $logger->info('Image Path Set');


		$import->setBehavior($behavior );
        $logger->info('Behavior Set '.$behavior);
         // $logger->info('Behavior Set Custom '.MagentoImport::FIELD_NAME_VALIDATION_STRATEGY."--".ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS);



        try {
            $file_data = file_get_contents(realpath($file));

            // $file_data = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $file_data);
           $file_data = preg_replace('/[^\P{C}\n]+/u', '',  $file_data);
           // $file_data = str_replace('"', '', $file_data);
            // $logger->info('File Encoding'.mb_detect_encoding($file_data));
           file_put_contents(realpath($file), $file_data);

            $importProductRawData = $this->csvProcessor->getData(realpath($file));
            //$logger->info('importProductRawData LogNew'.print_r($importProductRawData, true));
            // $fp = fopen(realpath($file), 'wb');
            // fputcsv($fp, $file_data);
            // fclose($fp);

            $ret = $import->setFile(realpath($file));
            //$import->isErrorLimitExceeded(false);
            //$import->hasFatalExceptions(false);




            // $logger->info('Array Log'.print_r($ret, true));

            $logger->info('File Set'.realpath($file));
             // $logger->info('File DataNew'.$file_data);
            $this->dataPersistor->set('is_custom_import', true);
            $logger->info('Execution Started');
//            try {
//                $this->validate->execute();
//            }catch (\Exception $exception) {
//
//            }
            $result = $import->execute();
            $this->flushCache();
            $this->dataPersistor->clear('is_custom_import');

            if ($result) {
                $logger->info('The import was successful');
                $this->logger->info('<info>The import was successful.</info>');
                $this->logger->info("Log trace:");
                //$this->logger->info("log".print_r($import,true));
                $this->logger->info($import->getFormattedLogTrace());
                $log = $import->getFormattedLogTrace();
                $logger->info('Log trace: '.$log);
                $logger->info('Validation Strategy: '.$import->getData('validation_strategy'));

            } else {
                $this->logger->info('<error>Import failed.</error>');
                $errors = $import->getErrors();
                foreach ($errors as $error) {
                    $this->logger->info('<error>' . $error->getErrorMessage() . ' - ' .$error->getErrorDescription() . '</error>');

                    $logger->info("Error Message: ".$error->getErrorMessage());
                    $logger->info("Error Description: ".$error->getErrorDescription());
                }

            }

        } catch (FileNotFoundException $e) {
            $this->dataPersistor->clear('is_custom_import');
            $logger->info("File not found");
            $this->logger->info('<error>File not found.</error>');

        } catch (\InvalidArgumentException $e) {
            $this->dataPersistor->clear('is_custom_import');
            $logger->info("Invalid Argument");
           $this->logger->info('<error>Invalid Argument.</error>');
        }
    }

    /**
     * @return RCCsv\Cron\Model\Import
     */
    protected function getImportModel()
    {
      return $this->objectManager->create('RCCsv\Cron\Model\Import');
    }

    /**
     * clean cache after save data in configuration
     */
    public function flushCache()
    {
        $_types = [
            'config',
            'full_page',
        ];
        foreach ($_types as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
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

