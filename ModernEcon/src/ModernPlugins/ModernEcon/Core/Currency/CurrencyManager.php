<?php

/*
 * ModernEcon
 *
 * Copyright (C) 2019 ModernPlugins
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace ModernPlugins\ModernEcon\Core\Currency;

use Generator;
use InvalidStateException;
use Logger;
use ModernPlugins\ModernEcon\Generated\Queries;
use ModernPlugins\ModernEcon\Master\MasterManager;
use ModernPlugins\ModernEcon\Utils\AwaitDataConnector;

final class CurrencyManager{
	/** @var Logger */
	private $logger;
	/** @var AwaitDataConnector */
	private $db;
	/** @var MasterManager */
	private $masterManager;

	/** @var Currency[] */
	private $currencies;

	public static function create(Logger $logger, AwaitDataConnector $db, MasterManager $masterManager, bool $creating) : Generator{
		if($creating){
			yield $db->executeGeneric(Queries::MODERNECON_CORE_CURRENCY_CREATE_CURRENCY);
			yield $db->executeGeneric(Queries::MODERNECON_CORE_CURRENCY_CREATE_SUBCURRENCY);
		}

		$manager = new CurrencyManager();
		$manager->logger = $logger;
		$manager->db = $db;
		$manager->masterManager = $masterManager;
		yield $manager->loadCurrencies();
		return $manager;
	}

	private function loadCurrencies() : Generator{
		$this->currencies = yield Currency::loadAll($this->db);
	}

	public function createCurrency(string $name, string $subName, string $symbolBefore, string $symbolAfter) : Generator{
		if(!$this->masterManager->isMaster()){
			throw new InvalidStateException("Currencies can only be created by the master server");
		}

		$id = yield $this->db->executeInsert(Queries::MODERNECON_CORE_CURRENCY_ADD_CURRENCY, [
			"name" => $name,
		]);
		$currency = new Currency($id, $name);

		yield $this->createSubcurrency($currency, $subName, $symbolBefore, $symbolAfter, 1);


		return $currency;
	}

	public function createSubcurrency(Currency $currency, string $name, string $symbolBefore, string $symbolAfter, int $magnitude) : Generator{
		$id = yield $this->db->executeInsert(Queries::MODERNECON_CORE_CURRENCY_ADD_SUBCURRENCY, [
			"name" => $name,
			"currency" => $currency->getId(),
			"symbolBefore" => $symbolBefore,
			"symbolAfter" => $symbolAfter,
			"magnitude" => $magnitude,
		]);
		$subcurrency = new Subcurrency($id, $name, $currency, $symbolBefore, $symbolAfter, 1);
		$currency->setSubcurrencies($currency->getSubcurrencies() + [$id => $subcurrency]);
	}

	private function __construct(){
	}
}
