<?php
// This migration clears the total_yearly_cost table as the calculation up to this point was incorrect

$db->exec('DELETE FROM total_yearly_cost');
