<?php
/***************************************************************************
 *   Copyright (C) 2005-2006 by Anton E. Lebedevich                        *
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
	final class FullTextSearch extends FullText
	{
		public function toDialectString(Dialect $dialect)
		{
			return
				$dialect->fullTextSearch(
					$this->field, 
					$this->words, 
					$this->logic
				);
		}
	}
?>