define([
  "dojo","dojo/_base/declare",
  "dojo/dom-style",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock",
  "ebg/zone"
],
function (dojo, declare, domStyle) {
  var cardwidth = 84;
  var cardheight = 127;
  var cardmargin = 5;

  // Get card unique identifier based on its color and value
  function getCardUniqueId(color, value) {
    return (color - 1) * 13 + (value - 1);
  }

  function getCardImgLoc(color, value) {
    return (color - 1) * 13 + (value - 1);
  }

  function createCardStock(gui, container) {
    var stock = new ebg.stock();
    stock.create( gui, container, cardwidth, cardheight );
    stock.image_items_per_row = 13;
    // Create cards types:
    for (var color = 1; color <= 4; color++) {
        for (var value = 1; value <= 13; value++) {
            // Build card type id
            var card_type_id = getCardUniqueId(color, value);
            stock.addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/cards.png', getCardImgLoc(color, value));
        }
    }
    return stock;
  }

  var GametableRow = declare(null, {
    bottomRow : null,
    topRow : null,

    constructor : function(gui, container) {
      var bottomContainer = container + '_bottom_container';
      var topContainer = container + '_top_container';

      domStyle.set(bottomContainer, 'width', (6 * cardwidth + 6 * cardmargin) + 'px');
      domStyle.set(topContainer, 'width', (7 * cardwidth + 6 * cardmargin) + 'px');

      this.bottomRow = createCardStock(gui, $(container + '_bottom'));
      this.topRow = createCardStock(gui, $(container + '_top'));

      this.bottomRow.setSelectionMode(0);
      this.bottomRow.autowidth = true;
      this.topRow.setSelectionMode(0);
      this.topRow.autowidth = true;
    },

    getRow : function(value) {
      if (value <= 6) {
        return this.bottomRow;
      } else {
        return this.topRow;
      }
    },

    addCard : function(color, value, id, from) {
      if (value <= 6) {
        // Since the row of bottom cards will expand, we need to move existing cards to the right manually to avoid animation.
        for (var item of this.bottomRow.getAllItems()) {
          var itemDiv = this.bottomRow.getItemDivId(item.id);
          var newLeft = domStyle.get(itemDiv, 'left') + cardwidth + cardmargin;
          domStyle.set(itemDiv, 'left', newLeft + 'px');
        }
      }

      this.getRow(value).addToStockWithId(getCardUniqueId(color, value), id, from);
    },

    removeAll : function() {
      this.bottomRow.removeAll();
      this.topRow.removeAll();
    },
  });

  return {
    getCardImgLoc: getCardImgLoc,
    getCardUniqueId: getCardUniqueId,
    createCardStock: createCardStock,
    GametableRow: GametableRow,
  } 
});