<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Ristiseiska implementation: © Sami Kalliomäki <sami@kalliomaki.me>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * ristiseiska.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class Ristiseiska extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels( array( 
            "penalty_holder" => 10,
            "target_score" => 100,
        ) );

        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );
	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "ristiseiska";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue("penalty_holder", 0);
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // TODO: setup the initial game situation here
        // Create cards
        $cards = array ();
        foreach ( $this->colors as $color_id => $color ) {
            // spade, heart, diamond, club
            for ($value = 1; $value <= 13; $value ++) {
                //  A, 2, 3, 4, ... K
                // nbr = how many
                $cards [] = array ('type' => $color_id,'type_arg' => $value,'nbr' => 1 );
            }
        }

        $this->cards->createCards( $cards, 'deck' );

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        $result = array_merge($result, $this->getHandInfo($current_player_id));
        
        // TODO: Gather all information about current game situation (visible by player $current_player_id).
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        $players = self::loadPlayersBasicInfos();
        $player_count = count($players);
        $card_count = intdiv(52, $player_count);

        $values = [];
        foreach ( $players as $player_id => $player ) {
            array_push($values, $this->cards->countCardInLocation('hand', $player_id));
        }
        $round_progress = 100 - min($values) / $card_count * 100;

        $get_score = function($x): int {
            return $x['player_score'];
        };        

        $target_score = self::getGameStateValue('target_score');
        if ($target_score >= 0) {
            // Just to check we are not dividing by zero or anything crazy.
            $target_score = -1;
        }
        $min_score = min(array_map($get_score, $this->getScores()));
        $rounds_remaining = ceil(($target_score-$min_score) / -25);

        $game_progress = $min_score / $target_score * 100;
        $remaining_game_progress = $min_score < 0 ? 1 - $min_score / $target_score : 1;

        return $game_progress + $remaining_game_progress * $round_progress / $rounds_remaining;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    private function hasPlayableCards($player_id) {
        return count($this->getPlayableCards($player_id)) >= 1;
    }

    private function updatePlayableCards() {
        $players = self::loadPlayersBasicInfos();

        $cards_on_table = $this->cards->getCardsInLocation('cardsontable');

        foreach ( $players as $player_id => $player ) {
            self::notifyPlayer($player_id, 'playableCards', '', array('playable' => $this->getPlayableCards($player_id, $cards_on_table)));
        }
    }

    private function getPlayableCards($player_id, $cards_on_table = null) {
        $hand = $this->cards->getPlayerHand($player_id);

        if ($cards_on_table == null) {
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
        }

        $playable = [];
        foreach ( $hand as $card_id => $card ) {
            if ($this->isCardPlayable($card, $cards_on_table)) {
                array_push($playable, $card_id);
            }
        }
        return $playable;
    }

    private function isCardPlayable($card, $cards_on_table = null) {
        $color = $card['type'];
        $value = $card['type_arg'];

        if ($value == 7) {
            return true;
        }

        $check_value = 0;
        if ($value <= 6) {
            $check_value = $value + 1;
        } else if ($value == 8) {
            $check_value = 6;
        } else {
            $check_value = $value - 1;
        }

        if ($cards_on_table == null) {
            return count($this->cards->getCardsOfTypeInLocation($color, $check_value, 'cardsontable')) >= 1;
        } else {
            foreach ($cards_on_table as $card_id => $card) {
                if ($card['type'] == $color && $card['type_arg'] == $check_value) {
                    return true;
                }
            }

            return false;
        }
    }

    private function playCardInternal($player_id, $card_id, $card) {
        $this->cards->moveCard($card_id, 'cardsontable');

        // And notify
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays <span class="suit_${color}">${value_displayed}${color_displayed}</span>'), array (
                'i18n' => array ('value_displayed' ),'card_id' => $card_id,'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id),'value' => $card['type_arg'],
                'value_displayed' => $this->values_label[$card['type_arg']],'color' => $card['type'],
                'color_displayed' => $this->colors[$card['type']]['symbol'], 'remaining_cards' => $this->countCardsInHand($player_id) ));
        $this->updatePlayableCards();
    }

    private function decrementScore($players, $player_id, $points) {
        $player_id_sql = self::escapeStringForDB($player_id);
        $points_sql = self::escapeStringForDB($points);

        $sql = "UPDATE player SET player_score=player_score-$points_sql WHERE player_id='$player_id_sql'";
        self::DbQuery($sql);
        self::notifyAllPlayers("losePoints", clienttranslate('${player_name} loses ${points} points'), array (
                'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                'points' => $points, 'new_score' => $this->getScores()[$player_id]['player_score'] ));
    }

    private function getScores() {
        return self::getCollectionFromDB("SELECT player_id, player_score FROM player");
    }

    private function getHandInfo($player_id) {
        $result = array();

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation( 'hand', $player_id );

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation( 'cardsontable' );
        $result['playable'] = $this->getPlayableCards($player_id);
        $result['penalty_holder'] = self::getGameStateValue("penalty_holder");
        $result['cardcounts'] = array();

        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $ply_id => $player ) {
            $result['cardcounts'][$ply_id] = $this->countCardsInHand($ply_id);
        }

        return $result;
    }

    private function countCardsInHand($player_id) {
        return $this->cards->countCardInLocation('hand', $player_id);
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in ristiseiska.action.php)
    */

    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */

    function playCard($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();
        // XXX check rules here
        $card = $this->cards->getCard($card_id);
        $location = $card['location'];
        $location_arg = $card['location_arg'];
        $value = $card ['type_arg'];

        if ($location != 'hand' || $location_arg != $player_id) {
            throw new BgaUserException(self::_("Card is not in the player's hand."));
        }
        if (!$this->isCardPlayable($card)) {
            throw new BgaUserException(self::_("This card cannot be played yet."));
        }

        $this->playCardInternal($player_id, $card_id, $card);
        
        if (count($this->cards->getCardsInLocation('hand', $player_id)) >= 1 && ($value == 1 || $value == 13)) {
            self::giveExtraTime($player_id);
            // Next player
            $this->gamestate->nextState('mayContinue');
        } else {
            // Next player
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function pass() {
        self::checkAction("pass");
        $player_id = self::getActivePlayerId();

        if ($this->gamestate->state()['mustPlayIfPossible'] && $this->hasPlayableCards($player_id)) {
            throw new BgaUserException(self::_("You must play a card if possible."));
        }

        if ($this->gamestate->state()['mustPlayIfPossible']) {
            self::setGameStateValue("penalty_holder", $player_id);
            self::notifyAllPlayers("movePenalty", clienttranslate('${player_name} must pass and takes the penalty token'), array (
                'player_id' => $player_id,'player_name' => self::getActivePlayerName()));
        }

        // Next player
        $this->gamestate->nextState('nextPlayer');
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

    function argPlayerTurn() {
        return array(
            'canPass' => !$this->gamestate->state()['mustPlayIfPossible'] || !$this->hasPlayableCards($this->getActivePlayerId())
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */
    
    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */

    function stNewHand() {
        $this->cards->moveAllCardsInLocation('cardsontable', 'deck');
        $this->cards->moveAllCardsInLocation('hand', 'deck');
        self::setGameStateValue("penalty_holder", 0);

        // Shuffle deck
        $this->cards->shuffle('deck');
        // Deal 13 cards to each players
        $players = self::loadPlayersBasicInfos();
        $player_count = count($players);

        foreach ( $players as $player_id => $player ) {
            $card_count = intdiv(52, $player_count) + ((52 % $player_count >= $player['player_no']) ? 1 : 0);
            $cards = $this->cards->pickCards($card_count, 'deck', $player_id);
        }

        foreach ( $players as $player_id => $player ) {
            self::notifyPlayer($player_id, "newHand", clienttranslate('A new hand is dealt'), $this->getHandInfo($player_id));
        }

        $cards_of_type = $this->cards->getCardsOfType(3, 7);
        $seven_of_clubs = reset($cards_of_type);
        $starting_player = $seven_of_clubs['location_arg'];

        $this->playCardInternal($starting_player, $seven_of_clubs['id'], $seven_of_clubs);

        // Activate first player (which is in general a good idea :) )
        $this->gamestate->changeActivePlayer($starting_player);
        self::activeNextPlayer();
        $this->gamestate->nextState("");
    }

    function stNextPlayer() {
        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $player_id => $player ) {
            if ($this->cards->countCardInLocation('hand', $player_id) == 0) {
                self::notifyAllPlayers('endOfRound', clienttranslate('${player_name}\'s hand is empty and the round ends'), array (
                    'player_id' => $player_id, 'player_name' => self::getPlayerNameById($player_id) ));

                $this->gamestate->nextState('endOfRound');
                return;
            }
        }

        // Standard case (not the end of the trick)
        // => just active the next player
        $player_id = self::activeNextPlayer();
        self::giveExtraTime($player_id);
        $this->gamestate->nextState('nextPlayer');
    }

    function stEndOfRound() {
        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $player_id => $player ) {
            $cards_left = $this->cards->getCardsInLocation('hand', $player_id);

            $total = 0;
            foreach ($cards_left as $card_id => $card) {
                $value = $card['type_arg'];
                $total += $value == 1 ? 14 : $value;
            }

            if ($player_id == self::getGameStateValue("penalty_holder")) {
                $total += 25;
            }

            $this->decrementScore($players, $player_id, $total);
        }

        $scores = $this->getScores();
        foreach ( $players as $player_id => $player ) {
            if ($scores[$player_id]['player_score'] <= $this->getGameStateValue('target_score')) {
                $this->gamestate->nextState('gameEnd');
                return;
            }
        }

        $this->gamestate->nextState('newHand');
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            $playable_cards = $this->getPlayableCards($active_player);

            if (count($playable_cards) >= 1) {
                $this->playCard($playable_cards[0]);
            } else {
                $this->pass();
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
