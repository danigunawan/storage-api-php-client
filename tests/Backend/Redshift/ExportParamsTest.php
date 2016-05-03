<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 03/05/16
 * Time: 09:45
 */
namespace Keboola\Test\Backend\Redshift;
use Keboola\Test\StorageApiTestCase;
use Keboola\Csv\CsvFile;

class ExportParamsTest extends StorageApiTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->_initEmptyTestBuckets();
    }

    /**
     * @param $exportOptions
     * @param $expectedResult
     * @dataProvider tableExportFiltersData
     */
    public function testTableExportAsyncRedshift($exportOptions, $expectedResult)
    {
        $importFile =  __DIR__ . '/../../_data/users.csv';
        $csvFile = new CsvFile($importFile);
        $tableId = $this->_client->createTableAsync($this->getTestBucketId(self::STAGE_IN, self::BACKEND_REDSHIFT), 'users', $csvFile, array(
            'columns' => $csvFile->getHeader(),
        ));

        $results = $this->_client->exportTableAsync($tableId, array_merge($exportOptions, array(
            'format' => 'raw',
        )));

        $exportedFile = $this->_client->getFile($results['file']['id'], (new \Keboola\StorageApi\Options\GetFileOptions())->setFederationToken(true));

        $this->assertTrue($exportedFile['isSliced']);
        $this->assertGreaterThan(0, $exportedFile['sizeBytes']);

        $manifest = json_decode(file_get_contents($exportedFile['url']), true);

        $s3Client = new \Aws\S3\S3Client([
            'credentials' => [
                'key' => $exportedFile['credentials']['AccessKeyId'],
                'secret' => $exportedFile['credentials']['SecretAccessKey'],
                'token' => $exportedFile['credentials']['SessionToken'],
            ],
            'version' => 'latest',
            'region' => $exportedFile['region']
        ]);
        $s3Client->registerStreamWrapper();

        $csv = "";
        foreach ($manifest['entries'] as $filePart) {
            $csv .= file_get_contents($filePart['url']);
        }

        $parsedData = Client::parseCsv($csv, false, "\t", "");
        $this->assertArrayEqualsSorted($expectedResult, $parsedData, 0);

        // Check S3 ACL and listing bucket
        $s3Client = new \Aws\S3\S3Client([
            'credentials' => [
                'key' => $exportedFile['credentials']['AccessKeyId'],
                'secret' => $exportedFile['credentials']['SecretAccessKey'],
                'token' => $exportedFile['credentials']['SessionToken'],
            ],
            'version' => 'latest',
            'region' => $exportedFile['region']
        ]);
        $bucket = $exportedFile["s3Path"]["bucket"];
        $prefix = $exportedFile["s3Path"]["key"];
        $objects = $s3Client->listObjects(array(
            "Bucket" => $bucket,
            "Prefix" => $prefix
        ));
        $this->assertEquals(3, count($objects["Contents"]));
        foreach($objects["Contents"] as $object) {
            $this->assertStringStartsWith($prefix, $object["Key"]);
        }
    }

}