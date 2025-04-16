# Play Editor
The Play Editor is a single static file WYPIWYG (What You **Play** Is What You
Get) [WordPress Playground](https://wordpress.org/playground/) blueprints
editor.

The .html file spins a Playground instance that generates a blueprint on the fly
if you perform some changes in `wp-admin`. It accepts the same `fragment`
(`#{...}`, `#cmVhbGx5Pw==`) and `blueprint-url=...` API accepted by
`playground.wordpress.net`.

## How to play with this

> [!IMPORTANT]
> The Play Editor is a work of proof and tested only with the most popular
plugins and themes. Please do not use it in production environments.

There is a [live demo](https://zaerl.com/play-editor/) available. If you want to
play with this on localhost, download and open that `index.html` file.

Once Playground has started, do whatever you want on WordPress, such as changing
options, installing plugins and themes, creating posts, etc. After this, select
the "Play Editor" menu. Inside that page, you will find the generated blueprint
`.json` file. In that page, you can open it on Playground, download it or copy
it on the clipboard.

The Play Editor can also be used as a standard plugin.

## How does this work
The Play Editor is composed of two things:

1. The `play-editor.php` (mu-)plugin.
2. The main `index.html` file that spins Playground.

The plugin intercepts various changes made by the user and generates a .json
file. It also sends the results back to the parent window using
`post_message_to_js`.

The `index.html` instead:

1. Decode a blueprint from URL parameters, if there is one.
2. Add three steps to the blueprint.
   1. It generates the `mu-plugins` folder.
   2. Write the Play Editor plugin there.
   3. Save the original `blueprint.json` file.
3. It listens to Playground messages to save on the clipboard, download or open
the JSON file.

## How to compile it
The `index.html` editor file is an *amalgamation* of more than one file. To
generate it, you must run `npm run compile`, or simply `npm i`. You can find the
source in the `source.html` file and the `play-editor` folder.

## Frequently asked questions

### Why?
Why not?

### Why are there no FS mounts?
To specify that it is just a prototype and should only be used to play with a
bit.

## Why are opening demanded in the parent window?
Because Playground is run inside an `<iframe>` and opening new tabs is
impossible.
