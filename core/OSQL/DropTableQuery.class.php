<?php
/***************************************************************************
 *   Copyright (C) 2006 by Konstantin V. Arkhipov                          *
 *   voxus@onphp.org                                                       *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup OSQL
	**/
	final class DropTableQuery extends QueryIdentification
	{
		private $name		= null;
		
		private $cascade	= false;
		
		public function getId()
		{
			throw new UnsupportedMethodException();
		}
		
		public function __construct($name, $cascade = false)
		{
			$this->name = $name;
			$this->cascade = (true === $cascade);
		}
		
		public function toString(Dialect $dialect)
		{
			return
				'DROP TABLE '.$dialect->quoteTable($this->name)
				.(
					$this->cascade
						? ' CASCADE'
						: ' RESTRICT'
				)
				.';';
		}
	}
?>