<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 22/05/14
 * Time: 16:38
 * To change this template use File | Settings | File Templates.
 */

use Keboola\Csv\CsvFile;

class Keboola_StorageApi_ComponentsTest extends StorageApiTestCase
{


	public function setUp()
	{
		parent::setUp();

		$components = new \Keboola\StorageApi\Components($this->_client);
		foreach ($components->listComponents() as $component) {
			foreach ($component['configurations'] as $configuration) {
				$components->deleteConfiguration($component['id'], $configuration['id']);
			}
		}
	}

	public function testComponentConfigCreate()
	{
		$components = new \Keboola\StorageApi\Components($this->_client);
		$components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setConfigurationId('main-1')
			->setName('Main')
			->setDescription('some desc')
		);

		$component = $components->getConfiguration('gooddata-writer', 'main-1');
		$this->assertEquals('Main', $component['name']);
		$this->assertEquals('some desc', $component['description']);
		$this->assertEmpty($component['configuration']);
		$this->assertEquals(0, $component['version']);
		$this->assertInternalType('int', $component['version']);
		$this->assertInternalType('int', $component['creatorToken']['id']);

		$components = $components->listComponents();
		$this->assertCount(1, $components);

		$component = reset($components);
		$this->assertEquals('gooddata-writer', $component['id']);
		$this->assertCount(1, $component['configurations']);

		$configuration = reset($component['configurations']);
		$this->assertEquals('main-1', $configuration['id']);
		$this->assertEquals('Main', $configuration['name']);
		$this->assertEquals('some desc', $configuration['description']);
	}

	public function testConfigurationNameShouldBeRequired()
	{
		try {
			$this->_client->apiPost('storage/components/gooddata-writer/configs', [
			]);
			$this->fail('Params should be invalid');
		} catch (\Keboola\StorageApi\ClientException $e) {
			$this->assertEquals('storage.components.validation', $e->getStringCode());
			$this->assertContains('name', $e->getMessage());
		}
	}

	public function testNonJsonConfigurationShouldNotBeAllowed()
	{
		try {
			$this->_client->apiPost('storage/components/gooddata-writer/configs', array(
				'name' => 'neco',
				'description' => 'some',
				'configuration' => '{sdf}',
			));
			$this->fail('Post invalid json should not be allowed.');
		} catch (\Keboola\StorageApi\ClientException $e) {
			$this->assertEquals(400, $e->getCode());
			$this->assertEquals('validation.invalidConfigurationFormat', $e->getStringCode());
		}
	}

	public function testComponentConfigCreateWithConfigurationJson()
	{
		$configuration = array(
			'queries' => array(
				array(
					'id' => 1,
					'query' => 'SELECT * from some_table',
				)
			),
		);
		$components = new \Keboola\StorageApi\Components($this->_client);
		$components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('gooddata-writer')
				->setConfigurationId('main-1')
				->setName('Main')
				->setDescription('some desc')
				->setConfiguration($configuration)
		);

		$config = $components->getConfiguration('gooddata-writer', 'main-1');

		$this->assertEquals($configuration, $config['configuration']);
		$this->assertEquals(0, $config['version']);
	}

	public function testComponentConfigCreateWithStateJson()
	{
		$state = array(
			'queries' => array(
				array(
					'id' => 1,
					'query' => 'SELECT * from some_table',
				)
			),
		);
		$components = new \Keboola\StorageApi\Components($this->_client);
		$components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('gooddata-writer')
				->setConfigurationId('main-1')
				->setName('Main')
				->setDescription('some desc')
				->setState($state)
		);

		$config = $components->getConfiguration('gooddata-writer', 'main-1');

		$this->assertEquals($state, $config['state']);
		$this->assertEquals(0, $config['version']);
	}

	public function testComponentConfigCreateIdAutoCreate()
	{
		$components = new \Keboola\StorageApi\Components($this->_client);
		$component = $components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('gooddata-writer')
				->setName('Main')
				->setDescription('some desc')
		);
		$this->assertNotEmpty($component['id']);
		$component = $components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('gooddata-writer')
				->setName('Main')
				->setDescription('some desc')
		);
		$this->assertNotEmpty($component['id']);
	}

	public function testComponentConfigUpdate()
	{
		$config = (new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setConfigurationId('main-1')
			->setName('Main');
		$components = new \Keboola\StorageApi\Components($this->_client);
		$newConfiguration = $components->addConfiguration($config);
		$this->assertEquals(0, $newConfiguration['version']);
		$this->assertEmpty($newConfiguration['state']);

		$newName = 'neco';
		$newDesc = 'some desc';
		$configurationData = array('x' => 'y');
		$config->setName($newName)
			->setDescription($newDesc)
			->setConfiguration($configurationData);
		$components->updateConfiguration($config);

		$configuration = $components->getConfiguration($config->getComponentId(), $config->getConfigurationId());

		$this->assertEquals($newName, $configuration['name']);
		$this->assertEquals($newDesc, $configuration['description']);
		$this->assertEquals($config->getConfiguration(), $configuration['configuration']);
		$this->assertEquals(1, $configuration['version']);

		$state = [
			'cache' => true,
		];
		$config = (new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setConfigurationId('main-1')
			->setDescription('neco')
			->setState($state);

		$updatedConfig = $components->updateConfiguration($config);
		$this->assertEquals($newName, $updatedConfig['name'], 'Name should not be changed after description update');
		$this->assertEquals('neco', $updatedConfig['description']);
		$this->assertEquals($configurationData, $updatedConfig['configuration']);
		$this->assertEquals($state, $updatedConfig['state']);

		$configuration = $components->getConfiguration($config->getComponentId(), $config->getConfigurationId());

		$this->assertEquals($newName, $configuration['name'], 'Name should not be changed after description update');
		$this->assertEquals('neco', $configuration['description']);
		$this->assertEquals($configurationData, $configuration['configuration']);
		$this->assertEquals($state, $configuration['state']);

		$config = (new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setConfigurationId('main-1')
			->setDescription('');

		$components->updateConfiguration($config);
		$configuration = $components->getConfiguration($config->getComponentId(), $config->getConfigurationId());
		$this->assertEquals('', $configuration['description'], 'Description can be set empty');
	}

	public function testComponentConfigsVersioning()
	{
		$config = (new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setConfigurationId('main-1')
			->setName('Main');
		$components = new \Keboola\StorageApi\Components($this->_client);
		$newConfiguration = $components->addConfiguration($config);
		$this->assertEquals(0, $newConfiguration['version']);
		$this->assertEmpty($newConfiguration['state']);

		$newName = 'neco';
		$newDesc = 'some desc';
		$configurationData = array('x' => 'y');
		$config->setName($newName)
			->setDescription($newDesc)
			->setConfiguration($configurationData);
		$components->updateConfiguration($config);

		$configuration = $components->getConfiguration($config->getComponentId(), $config->getConfigurationId());
		$this->assertEquals(1, $configuration['version']);

		$config = (new \Keboola\StorageApi\Options\Components\ListConfigurationVersionsOptions())
			->setComponentId($config->getComponentId())
			->setConfigurationId($config->getConfigurationId())
			->setInclude(array('name', 'state'));
		$result = $components->listConfigurationVersions($config);
		$this->assertCount(2, $result);
		$this->assertArrayHasKey('version', $result[0]);
		$this->assertEquals(0, $result[0]['version']);
		$this->assertArrayHasKey('name', $result[0]);
		$this->assertEquals('Main', $result[0]['name']);
		$this->assertArrayHasKey('state', $result[0]);
		$this->assertArrayNotHasKey('description', $result[0]);
		$this->assertArrayHasKey('version', $result[1]);
		$this->assertEquals(1, $result[1]['version']);
		$this->assertArrayHasKey('name', $result[1]);
		$this->assertEquals('neco', $result[1]['name']);

		$config = (new \Keboola\StorageApi\Options\Components\ListConfigurationVersionsOptions())
			->setComponentId($config->getComponentId())
			->setConfigurationId($config->getConfigurationId())
			->setInclude(array('name', 'configuration'))
			->setOffset(1)
			->setLimit(1);
		$result = $components->listConfigurationVersions($config);
		$this->assertCount(1, $result);
		$this->assertArrayHasKey('version', $result[0]);
		$this->assertEquals(1, $result[0]['version']);
		$this->assertArrayNotHasKey('state', $result[0]);
		$this->assertArrayHasKey('configuration', $result[0]);
		$this->assertEquals($newConfiguration, $result[0]['configuration']);

		$config = (new \Keboola\StorageApi\Options\Components\ListConfigurationVersionsOptions())
			->setComponentId($config->getComponentId())
			->setConfigurationId($config->getConfigurationId());
		$result = $components->getConfigurationVersion($config->getComponentId(), $config->getConfigurationId(), 1);
		$this->assertArrayHasKey('version', $result);
		$this->assertInternalType('int', $result['version']);
		$this->assertEquals(1, $result['version']);
		$this->assertInternalType('int', $result['creatorToken']['id']);
		$this->assertArrayHasKey('state', $result);
		$this->assertArrayHasKey('configuration', $result);
		$this->assertEquals($newConfiguration, $result[0]['configuration']);
		$result = $components->listConfigurationVersions($config);
		$this->assertCount(2, $result);

		$result = $components->rollbackConfiguration($config->getComponentId(), $config->getConfigurationId(), 0);
		$this->assertArrayHasKey('version', $result);
		$this->assertEquals(2, $result['version']);
		$result = $components->getConfigurationVersion($config->getComponentId(), $config->getConfigurationId(), 2);
		$this->assertArrayHasKey('name', $result);
		$this->assertEquals('Main', $result['name']);
		$result = $components->listConfigurationVersions($config);
		$this->assertCount(3, $result);

		$result = $components->createConfigurationFromVersion($config->getComponentId(), $config->getConfigurationId(), 1, 'New');
		$this->assertArrayHasKey('id', $result);
		$configuration = $components->getConfiguration($config->getComponentId(), $result['id']);
		$this->assertArrayHasKey('name', $configuration);
		$this->assertEquals('New', $configuration['name']);
		$this->assertArrayHasKey('description', $configuration);
		$this->assertEquals($newDesc, $configuration['description']);
		$this->assertArrayHasKey('version', $configuration);
		$this->assertEquals(0, $configuration['version']);
		$this->assertArrayHasKey('configuration', $configuration);
		$this->assertEquals($configurationData, $configuration['configuration']);

		$result = $components->createConfigurationFromVersion($config->getComponentId(), $config->getConfigurationId(), 0, 'New 2');
		$this->assertArrayHasKey('id', $result);
		$configuration = $components->getConfiguration($config->getComponentId(), $result['id']);
		$this->assertArrayHasKey('name', $configuration);
		$this->assertEquals('New 2', $configuration['name']);
		$this->assertArrayHasKey('description', $configuration);
		$this->assertEmpty($configuration['description']);
		$this->assertArrayHasKey('version', $configuration);
		$this->assertEquals(0, $configuration['version']);
		$this->assertArrayHasKey('configuration', $configuration);
		$this->assertEmpty($configuration['configuration']);
	}

	public function testComponentConfigsListShouldNotBeImplemented()
	{
		try {
			$this->_client->apiGet('storage/components/gooddata-writer/configs');
			$this->fail('Method should not be implemented');
		} catch (\Keboola\StorageApi\ClientException $e) {
			$this->assertEquals(501, $e->getCode());
			$this->assertEquals('notImplemented', $e->getStringCode());
		}
	}

	public function testListConfigs()
	{
		$components = new \Keboola\StorageApi\Components($this->_client);

		$configs = $components->listComponents();
		$this->assertEmpty($configs);


		$components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('gooddata-writer')
				->setConfigurationId('main-1')
				->setName('Main')
		);
		$components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('gooddata-writer')
				->setConfigurationId('main-2')
				->setConfiguration(array('x' => 'y'))
				->setName('Main')
		);
		$components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
				->setComponentId('provisioning')
				->setConfigurationId('main-1')
				->setName('Main')
		);

		$configs = $components->listComponents();
		$this->assertCount(2, $configs);

		$configs = $components->listComponents((new \Keboola\StorageApi\Options\Components\ListConfigurationsOptions())
			->setComponentType('writer'));

		$this->assertCount(2, $configs[0]['configurations']);
		$this->assertCount(1, $configs);

		$configuration = $configs[0]['configurations'][0];
		$this->assertArrayNotHasKey('configuration', $configuration);

		// list with configuration body
		$configs = $components->listComponents((new \Keboola\StorageApi\Options\Components\ListConfigurationsOptions())
			->setComponentType('writer')
			->setInclude(array('configuration'))
		);

		$this->assertCount(2, $configs[0]['configurations']);
		$this->assertCount(1, $configs);

		$configuration = $configs[0]['configurations'][0];
		$this->assertArrayHasKey('configuration', $configuration);
	}

	public function testDuplicateConfigShouldNotBeCreated()
	{
		$options = (new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setConfigurationId('main-1')
			->setName('Main');

		$components = new \Keboola\StorageApi\Components($this->_client);
		$components->addConfiguration($options);

		try {
			$components->addConfiguration($options);
			$this->fail('Configuration should not be created');
		} catch (\Keboola\StorageApi\ClientException $e) {
			$this->assertEquals('configurationAlreadyExists', $e->getStringCode());
		}

	}

	public function testPermissions()
	{
		$tokenId = $this->_client->createToken(array(), 'test');
		$token = $this->_client->getToken($tokenId);

		$client = new Keboola\StorageApi\Client(array(
			'token' => $token['token'],
			'url' => STORAGE_API_URL,
		));

		$components = new \Keboola\StorageApi\Components($client);
		try {
			$components->listComponents();
			$this->fail('List components should not be allowed');
		} catch (\Keboola\StorageApi\ClientException $e) {
			$this->assertEquals('accessDenied', $e->getStringCode());
		}

	}

	public function testTokenWithManageAllBucketsShouldHaveAccessToComponents()
	{
		$tokenId = $this->_client->createToken('manage', 'test components');
		$token = $this->_client->getToken($tokenId);

		$client = new Keboola\StorageApi\Client(array(
			'token' => $token['token'],
			'url' => STORAGE_API_URL,
		));
		$components = new \Keboola\StorageApi\Components($client);
		$componentsList = $components->listComponents();
		$this->assertEmpty($componentsList);

		$config = $components->addConfiguration((new \Keboola\StorageApi\Options\Components\Configuration())
			->setComponentId('gooddata-writer')
			->setName('Main'));

		$componentsList = $components->listComponents();
		$this->assertCount(1, $componentsList);
		$this->assertEquals($config['id'], $componentsList[0]['configurations'][0]['id']);

		$this->_client->dropToken($tokenId);
	}


}