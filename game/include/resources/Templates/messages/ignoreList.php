<script type="text/javascript" data-cmp-info="6">
    jQuery(function(){
        "use strict";

        var showIgnoreListError = function(error) {
            new Travian.Dialog.Dialog({preventFormSubmit: true})
                .setContent(error.message)
                .show();
        };

        var renderIgnoreList = function() {
            Travian.api('player/ignore', {
                data: {
                    method: 'render_ignore_list'
                },
                success: function(response) {
                    if (response.result !== undefined) {
                        jQuery('#ignore-list').html(response.result);
                    }
                },
                error: showIgnoreListError
            });
        };

        renderIgnoreList();
    });

	var unignoreUser = function(targetPlayer) {
		Travian.api('player/ignore', {
			data: {
				method: 'stop_ignore_target_player',
				renderIgnoreList: true,
				params: {
					targetPlayer: targetPlayer
				}
			},
			success: function(response) {
				if ((response.error === undefined || !response.error) && response.result !== undefined) {
					jQuery('#ignore-list').html(response.result);
				}
			},
			error: showIgnoreListError
		});

		return false;
	};
</script>

<div class="ignoreListContainer" id="ignore-list">
	</div>
<br/>
<span></span>
