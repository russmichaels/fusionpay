<?php

class SWIFT_ImportTable_PointAudit extends SWIFT_ImportTable {
	private $myDisplayName;
	private $tp_multiplier = 1;
	
	public function __construct(SWIFT_ImportManager $_SWIFT_ImportManagerObject) {
		$this->myDisplayName = str_replace("SWIFT_ImportTable_","",get_class($this));
		parent::__construct($_SWIFT_ImportManagerObject, $this->myDisplayName);

		$this->myTableName = TABLE_PREFIX . "userorders";

		if (!$this->TableExists($this->myTableName)) {
			$this->SetByPass(true);

			return false;
		}

		$this->tp_multiplier = $this->ImportManager->GetImportRegistry()->GetKey('database', 'tp_multiplier');

		return true;
	}

	public function __destruct() {
		parent::__destruct();

		return true;
	}

	public function Import() {
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		require_once(SWIFT_MODULESDIRECTORY."/supportpay/sp_globals.php");

		if ($this->GetOffset() == 0) {
			// Delete nothing for this one, any previously-migrated records were deleted by PointPackages.
		}

		$_count = 0;

		$this->DatabaseImport->QueryLimit("select userid, amount, itemid, dateorder ".
			" from ".TABLE_PREFIX."points where support_type = 'ticket'",
			$this->GetItemsPerPass(), $this->GetOffset());
		
		$RecContainer = array();
		while ($this->DatabaseImport->NextRecord()) {
			$RecContainer[] = $this->DatabaseImport->Record;
		}

		$this->ImportManager->GetImportRegistry()->GetNonCached('user');

		foreach ($RecContainer as $recData) {
			$_count++;

			$_newUserID = $this->ImportManager->GetImportRegistry()->GetKey('user', $recData['userid']);
			
			$this->Database->AutoExecute(TABLE_PREFIX."sp_user_payments",
				array(  'ticketid' => $recData['itemid'],
						'userid' =>  $_newUserID,
						'minutes' =>  -$recData['amount'] * $this->tp_multiplier,
						'tickets' =>  0,
						'rem_minutes' =>  0,
						'rem_tickets' =>  0,
						'paidby' => 'TicketPay',
						'comments' => 'Ticket '.$recData["itemid"],
						'packageid' => null,
						'created' => $recData["dateorder"],
						'cost' => 0,
						'currency' => $_SWIFT->Settings->Get('sp_currency'),
						'migrated' =>  SP_MIGRATED_TICKETPAY
						),
					'INSERT');
		}

		$this->GetImportManager()->AddToLog('Importing '.$this->myDisplayName.': ' . $_count . " record(s)", SWIFT_ImportManager::LOG_SUCCESS);

		return $_count;
	}

	protected function GetTotal() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_countContainer = $this->DatabaseImport->QueryFetch("select count(1) as totalitems from ".TABLE_PREFIX."points ".
			"where support_type='ticket'");
		if (isset($_countContainer['totalitems'])) {
			return $_countContainer['totalitems'];
		}

		return 0;
	}

	public function GetItemsPerPass() {
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		return 1000;
	}
}
?>
