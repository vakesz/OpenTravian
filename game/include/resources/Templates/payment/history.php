<?php

declare(strict_types=1);

/** The player's own purchases in this world. $vars['orders'] is a list of paymentOrder rows (arrays). */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$statusKeys = ['pending' => 'Pending', 'paid' => 'Success', 'failed' => 'Success2'];
?>
<div id="transactionHistory">
	<h3><?=$escape(T('PaymentWizard', 'Order history'));?></h3>
	<div class="scrollableContent">
		<?php if ($vars['orders'] === []): ?>
			<span class="none"><?=$escape(T('PaymentWizard', 'There are no orders for your avatar on this game world'));?></span>
		<?php else: ?>
			<table class="transparent">
				<thead><tr>
					<th><?=$escape(T('PaymentWizard', 'Order Date'));?></th>
					<th><?=$escape(T('PaymentWizard', 'Gold'));?></th>
					<th><?=$escape(T('PaymentWizard', 'Price'));?></th>
					<th><?=$escape(T('PaymentWizard', 'Payment'));?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($vars['orders'] as $order): ?>
					<tr>
						<td><?=$escape(\Core\Helper\TimezoneHelper::autoDateString((int) $order['created'], true));?></td>
						<td><?=(int) $order['gold'];?></td>
						<td><?=$escape(\Game\Payment\Money::format((int) $order['amountMinor'], (string) $order['currency']));?></td>
						<td><?=$escape(T('PaymentWizard', $statusKeys[$order['status']] ?? 'Cancelled'));?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
