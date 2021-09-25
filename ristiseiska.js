/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Ristiseiska implementation: © Sami Kalliomäki <sami@kalliomaki.me>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * ristiseiska.js
 *
 * Ristiseiska user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/zone"
],
function (dojo, declare) {
    return declare("bgagame.ristiseiska", ebg.core.gamegui, {
        constructor: function(){
            console.log('ristiseiska constructor');
              
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
            this.cardwidth = 84;
            this.cardheight = 127;
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            console.log( "Starting game setup" );

            this.playerTokenZones = {}
            this.cardcounts = {}
            
            this.tokenPark = new ebg.zone();
            this.tokenPark.create(this, 'token_park', 32, 32);

            // Setting up player boards
            for( var player_id in gamedatas.players )
            {
                var player = gamedatas.players[player_id];
                         
                // Setting up players boards if needed
                var player_board_div = $('player_board_'+player_id);
                dojo.place( this.format_block('jstpl_player_board', player), player_board_div );

                var zone = new ebg.zone();
                zone.create(this, 'token_zone_' + player_id, 32, 32);
                this.playerTokenZones[player_id] = zone;

                this.cardcounts[player_id] = new ebg.counter();
                this.cardcounts[player_id].create('cardcount_p'+player_id);
            }
            this.penaltyHolder = 0;

            // TODO: Set up your game interface here, according to "gamedatas"
            this.playerHand = this.createCardStock($('myhand'));
            this.gametable = Array(5);
            for (var color = 1; color <= 4; color++) {
                var gametable = this.createCardStock($('gametable_' + color));
                gametable.setSelectionMode(0);
                this.gametable[color] = gametable;
            }

            this.resetHandAndTable(gamedatas);

            dojo.connect( this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged' );
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },

        createCardStock : function(container) {
            var stock = new ebg.stock();
            stock.create( this, container, this.cardwidth, this.cardheight );
            stock.image_items_per_row = 13;
            // Create cards types:
            for (var color = 1; color <= 4; color++) {
                for (var value = 1; value <= 13; value++) {
                    // Build card type id
                    var card_type_id = this.getCardUniqueId(color, value);
                    stock.addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/cards.png', this.getCardImgLoc(color, value));
                }
            }
            return stock;
        },

        // Get card unique identifier based on its color and value
        getCardUniqueId : function(color, value) {
            return (color - 1) * 13 + (value - 1);
        },

        getCardImgLoc : function(color, value) {
            return (color - 1) * 13 + (value - 1);
        },

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if( this.isCurrentPlayerActive() )
            {
                if (args && args.canPass) {
                    this.addActionButton('pass_button', _('Pass'), 'onPass');
                }
            }
        },


        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

        playCardOnTable : function(player_id, color, value, card_id) {
            if (player_id == this.player_id) {
                this.playerHand.removeFromStockById(card_id);
                this.gametable[color].addToStockWithId(this.getCardUniqueId(color, value), card_id, this.playerHand.getItemDivId(card_id));
            } else {
                this.gametable[color].addToStockWithId(this.getCardUniqueId(color, value), card_id, 'player_board_' + player_id);
            }
        },

        updatePlayableCards : function(playable) {
            for (var card of this.playerHand.getAllItems()) {
                var cardDiv = this.playerHand.getItemDivId(card.id);


                if (playable.includes(parseInt(card.id))) {
                    dojo.removeClass(cardDiv, 'unplayable');
                } else {
                    dojo.addClass(cardDiv, 'unplayable');
                }
            }
        },

        slidePenaltyTokenTo : function(player_id) {
            for (var ply_id in this.playerTokenZones) {
                this.playerTokenZones[ply_id].removeAll(false);
            }
            this.tokenPark.removeAll(false);

            if (player_id != 0) {
                this.playerTokenZones[player_id].placeInZone('penalty_token', 1);
            } else {
                this.tokenPark.placeInZone('penalty_token', 1);
            }
        },

        resetHandAndTable : function(gamedatas) {
            this.playerHand.removeAll();
            for (var i = 1; i <= 4; i++) {
                this.gametable[i].removeAll();
            }

            // Cards in player's hand
            for ( var i in gamedatas.hand) {
                var card = gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Cards played on table
            for (var i in gamedatas.cardsontable) {
                var card = gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                this.gametable[color].addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            for(var player_id in gamedatas.cardcounts) {
                this.cardcounts[player_id].setValue(gamedatas.cardcounts[player_id]);
            }


            this.slidePenaltyTokenTo(gamedatas.penalty_holder);
            this.updatePlayableCards(gamedatas.playable);
        },

        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
        
        /* Example:
        
        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/ristiseiska/ristiseiska/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

        onPlayerHandSelectionChanged : function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                var action = 'playCard';
                if (this.checkAction(action, true)) {
                    // Can play a card
                    var card_id = items[0].id;                    
                    this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                        id : card_id,
                        lock : true
                    }, this, function(result) {
                    }, function(is_error) {
                    });

                    this.playerHand.unselectAll();
                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        onPass: function() {
            var action = 'pass';
            if (this.checkAction(action, true)) {
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                    lock : true
                }, this, function(result) {
                }, function(is_error) {
                });
            }
        },

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your ristiseiska.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // TODO: here, associate your game notifications with local methods
            
            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            
            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            // 

            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('playCard', this, "notif_playCard");
            dojo.subscribe('playableCards', this, "notif_playableCards");
            dojo.subscribe('movePenalty', this, "notif_movePenalty");
            dojo.subscribe('losePoints', this, "notif_losePoints");

            this.notifqueue.setSynchronous('playCard', 1000);
            this.notifqueue.setSynchronous('movePenalty', 1000);
            this.notifqueue.setSynchronous('endOfRound', 3000);
            this.notifqueue.setSynchronous('losePoints', 1000);
        },

        notif_newHand : function(notif) {
            this.resetHandAndTable(notif.args)
        },

        notif_playCard : function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);
            this.cardcounts[notif.args.player_id].toValue(notif.args.remaining_cards);
        },

        notif_playableCards : function(notif) {
            // Play a card on the table
            this.updatePlayableCards(notif.args.playable);
        },

        notif_movePenalty : function(notif) {
            this.slidePenaltyTokenTo(notif.args.player_id);
        },

        notif_losePoints : function(notif) {
            console.log('Hello World!');
            this.scoreCtrl[notif.args.player_id].toValue(notif.args.new_score);
        },
   });
});
