# Helper Classes

The JavaScript code in `assets/frontend-loggedin.js` dynamically adds and removes classes to the `<body>` element based on user interactions and the state of the page. Here's a summary of the classes that are added to `<body>` and under what conditions:

1. **rpb-active**:
   - Added once the DOM content is fully loaded to indicate that the RockPageBuilder scripts are active and have modified the page. This is done in a [DOMContentLoaded](file:///Users/bernhard/baumrock/baumrock.com/site/modules/RockPageBuilder/assets/frontend-loggedin.js#202%2C28-202%2C28) event listener at the end of the script.

2. **.no-sortable**:
   - Added when the sortable functionality is disabled either through the `_RockSortable.prototype.disable` method or initially if the sortable is not enabled.
   - Removed when the sortable functionality is enabled through the `_RockSortable.prototype.enable` method.

3. **is-sortable**:
   - Added when the sortable functionality is enabled through the `_RockSortable.prototype.enable` method.
   - Removed when the sortable functionality is disabled through the `_RockSortable.prototype.disable` method.

4. **rpb-reloading**:
   - Added by the `_RockSortable.prototype.showSpinner` method right before a loading spinner is displayed. This typically occurs during a page reload triggered by a successful sort operation that requires a page refresh.

5. **grabbing**:
   - Added to indicate that an item is being dragged. It is added in the [onChoose](file:///Users/bernhard/baumrock/baumrock.com/site/modules/RockPageBuilder/assets/frontend-loggedin.js#176%2C7-176%2C7) event handler of the Sortable initialization and removed in the [onUnchoose](file:///Users/bernhard/baumrock/baumrock.com/site/modules/RockPageBuilder/assets/frontend-loggedin.js#194%2C7-194%2C7) event handler.

6. **no-alfred**:
   - Temporarily added during a drag operation to possibly indicate a mode where certain UI elements or interactions are disabled. It is added in the [onChoose](file:///Users/bernhard/baumrock/baumrock.com/site/modules/RockPageBuilder/assets/frontend-loggedin.js#176%2C7-176%2C7) event handler and removed in both the [onEnd](file:///Users/bernhard/baumrock/baumrock.com/site/modules/RockPageBuilder/assets/frontend-loggedin.js#186%2C7-186%2C7) and [onUnchoose](file:///Users/bernhard/baumrock/baumrock.com/site/modules/RockPageBuilder/assets/frontend-loggedin.js#194%2C7-194%2C7) event handlers if it was added by the drag operation.

These classes are used to control the appearance and functionality of the page, particularly in relation to the drag-and-drop sorting feature and the loading state indication.
