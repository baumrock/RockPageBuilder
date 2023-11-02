# Block Spacings

One problem that comes with a page builder approach is that all blocks need to fit together from a design perspective. There are designs where this is really easy, but there are also designs where this can get tricky.

Please have a look at this JSFiddle: https://jsfiddle.net/qyot6e7s/

## Simple Setup

The simplest setup is if you have sections that are all independent from each other and all separated with the same space on top and on the bottom. In this case you can simply apply a `margin-top` and `margin-bottom` of let's say 100px to each block (section) and the browser will take care of making these elements overlap properly so that you still get 100px between the content of two blocks and not 100+100.

## Medium Setup

It gets more complicated as soon as you have sections with backgrounds where you want to remove the space between two sections if a section with background is followed by a section with some background. In that case we need some CSS to remove margins if an element with background is followed by an element with background.

The problem with that approach is that you never know which sections your client adds to the website. And as soon as he/she adds more sections with the same background after each other the spacings start to look ugly because they will stack up to 100+100 = 200px.

This is shown in the `Medium 2` example of the fiddle.

<img src=problem.png class=blur>

## Advanced

The advanced example shows one possible solution to that problem, where some helper classes are applied via JavaScript on page load. The problem is that there is simply no possibility to target those sections from CSS. So we need helper classes.

Those helper classes could come from PHP, but that would have the drawback that sorting from the frontend via drag and drop would break as soon as the order of the sections is changed. A JavaScript solution can be triggered whenever the sort order changed and would therefore make sure that block spacings are correct even if the user applies some changes to the page.
