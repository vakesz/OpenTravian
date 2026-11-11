<div class="auctionsLocked">
    <div class="lockedIcon"><i class="lock"></i></div>
    <h3>Auctions locked</h3>
    <div class="barWrapper"><div class="bar" style="width: <?= (int)$vars['progress']; ?>%;"></div></div>
    <div class="barSubText">
        <div>Finish 10 <a href="/hero/adventures">adventures</a> to unlock the auctions</div>
        <div><?= (int)$vars['completed']; ?>/10</div>
    </div>
</div>
