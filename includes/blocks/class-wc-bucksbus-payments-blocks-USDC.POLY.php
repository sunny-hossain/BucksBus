<?php

/**
 * BucksBus Payments Blocks integration
 */
final class BucksBus_Gateway_Blocks_Support_USDC_POLY extends BucksBus_Gateway_Blocks_Support
{
	public function __construct()
	{
		parent::__construct('USDC.POLY');
	}
}
