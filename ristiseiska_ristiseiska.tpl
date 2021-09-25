{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Ristiseiska implementation: © Sami Kalliomäki <sami@kalliomaki.me>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    ristiseiska_ristiseiska.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
    
    Please REMOVE this comment before publishing your game on BGA
-->


<div id="myhand_wrap" class="whiteblock">
    <h3>{MY_HAND}</h3>
    <div id="myhand">
    </div>
</div>

<div class="whiteblock relative_parent">
    <h3>{GAME_TABLE}</h3><div id="token_park" class="token_zone"><div id="penalty_token"></div></div>
    <!-- BEGIN gametable -->
    <!-- Annoyingly there cannot be spaces here to avoid whitespace in the middle of the card row. -->
    <div id="gametable_{I}"><div id="gametable_{I}_bottom_container" class="inline_card_row_container bottom_row"><div id="gametable_{I}_bottom" class="inline_card_row"></div></div><div id="gametable_{I}_top_container" class="inline_card_row_container top_row"><div id="gametable_{I}_top" class="inline_card_row"></div></div></div>
    <!-- END gametable -->
</div>




<script type="text/javascript">

// Javascript HTML templates

/*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${MY_ITEM_ID}"></div>';

*/
var jstpl_player_board = '<div class="cp_board">\
    <span>{CARDS_IN_HAND}: </span><span id="cardcount_p${id}">0</span>\
    <div class="token_zone"><div id="token_zone_${id}"></div></div>\
</div>';

</script>

{OVERALL_GAME_FOOTER}
