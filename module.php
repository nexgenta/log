<?php

/*
 * Copyright 2010 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

uses('module');

class LogModule extends Module
{
	public $latestVersion = 2;
	public $moduleId = 'com.nexgenta.log';
	
	public static function getInstance($args = null, $className = null, $defaultDbIri = null)
	{
		return Model::getInstance($args, ($className ? $className : 'LogModule'), ($defaultDbIri ? $defaultDbIri : LOG_IRI));
	}

	public function updateSchema($targetVersion)
	{
		if($targetVersion == 1)
		{
			$t = $this->db->schema->tableWithOptions('log_entry', DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('entry_id', DBTable::SERIAL, null, DBTable::NOT_NULL, null, 'Log entry identifier');
			$t->columnWithSpec('entry_timestamp', DBTable::DATETIME, null, DBTable::NOT_NULL, null, 'Timestamp of the entry');
			$t->columnWithSpec('entry_cluster', DBTable::VARCHAR, 64, DBTable::NULLS, null, 'The name of the cluster associated with this entry, if any');
			$t->columnWithSpec('entry_instance', DBTable::VARCHAR, 255, DBTable::NULLS, null, 'The name of the instance associated with this entry, if any');
			$t->columnWithSpec('entry_scheme', DBTable::VARCHAR, 32, DBTable::NULLS, null, 'The scheme of the user associated with this entry, if any');
			$t->columnWithSpec('entry_uuid', DBTable::UUID, null, DBTable::NULLS, null, 'The UUID of the user associated with this entry, if any');
			$t->columnWithSpec('entry_pid', DBTable::INT, null, DBTable::NOT_NULL|DBTable::UNSIGNED, null, 'The ID of the logging process');
			$t->columnWithSpec('entry_priority', DBTable::ENUM, array('EMERG','ALERT','CRIT','ERR','WARNING','NOTICE','INFO','DEBUG'), DBTable::NOT_NULL, 'INFO', 'The priority associated with this entry');
			$t->columnWithSpec('entry_facility', DBTable::VARCHAR, 32, DBTable::NULLS, null, 'The name of the facility associated with this entry, if any');
			$t->columnWithSpec('entry_file', DBTable::TEXT, null, DBTable::NULLS, null, 'The source file associated with this entry, if any');
			$t->columnWithSpec('entry_line', DBTable::INT, null, DBTable::NULLS|DBTable::UNSIGNED, null, 'The line number associated with this entry, if any');
			$t->columnWithSpec('entry_message', DBTable::TEXT, null, DBTable::NULLS, null, 'The log messsage');
			$t->indexWithSpec(null, DBIndex::PRIMARY, 'entry_id');
			$t->indexWithSpec('entry_cluster', DBIndex::INDEX, 'entry_cluster');
			$t->indexWithSpec('entry_instance', DBIndex::INDEX, 'entry_instance');
			$t->indexWithSpec('entry_scheme', DBIndex::INDEX, 'entry_scheme');
			$t->indexWithSpec('entry_uuid', DBIndex::INDEX, 'entry_uuid');
			$t->indexWithSpec('entry_priority', DBIndex::INDEX, 'entry_priority');
			$t->indexWithSpec('entry_facility', DBIndex::INDEX, 'entry_facility');
			return $t->apply();
		}
		if($targetVersion == 2)
		{
			$t = $this->db->schema->tableWithOptions('log_count', DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('log_cluster', DBTable::VARCHAR, 64, DBTable::NULLS, null, 'The name of the cluster being counted');
			$t->columnWithSpec('log_instance', DBTable::VARCHAR, 255, DBTable::NULLS, null, 'The name of the instance being counted');
			$t->columnWithSpec('log_count', DBTable::INT, null, DBTable::NOT_NULL|DBTable::UNSIGNED, null, 'The count of log entries');
			$t->indexWithSpec('log_cluster', DBIndex::INDEX, 'log_cluster');
			$t->indexWithSpec('log_instance', DBIndex::INDEX, 'log_instance');
			return $t->apply();
		}
	}
}
