<?php

namespace App\Query;
use Amet\SimpleORM\BaseQuery as Query;


class Transaction extends Query
{
	protected $table = "transactions";
	protected $default_key = "uuid";

	protected function user()
	{
		$this->hasOne('users','uuid','user_uuid','user');
	}

	protected function account_source()
	{
		$this->hasOne('accounts','uuid','account_source_uuid','account_source');
	}

	protected function account_destination()
	{
		$this->hasOne('accounts','uuid','account_destination_uuid','account_destination');
	}

}