<?php

require_once __DIR__.'/../vendor/autoload.php';

class BackOfficeNavigationTest extends \PrestaShop\TestCase\LazyTestCase
{
	public function testLogin()
	{
		$this->shop->getBackOfficeNavigator()->login();
	}

	public function testMenuLinks()
	{
		$links = $this->shop->getBackOfficeNavigator()->getMenuLinks();

		$this->assertArrayHasKey('AdminDashboard', $links);
		$this->assertArrayHasKey('AdminProducts', $links);
		$this->assertArrayHasKey('AdminCustomers', $links);
		$this->assertArrayHasKey('AdminOrders', $links);
		$this->assertArrayHasKey('AdminModules', $links);

		$this->shop->getBackOfficeNavigator()->visit('AdminProducts');
	}

	/**
	* @expectedException PrestaShop\Exception\AdminControllerNotFoundException
	*/
	public function testNonExistingMenuLink()
	{
		$this->shop->getBackOfficeNavigator()->visit('ThisControllerIsVeryUnlikelyToExist');
	}
}
