<?= getButton(
    [
        'type' => 'submit',
        'value' => T('MarketPlace', 'prepare'),
        'id' => 'enabledButton',
        'class' => 'textButtonV1 green prepare',
        'onclick' => "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}",
        'tabindex' => '10',
        'onfocus' => "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;",
    ],
    ['data' => ['type' => 'submit', 'value' => T('MarketPlace', 'prepare')]],
    T('MarketPlace', 'prepare')
); ?>
