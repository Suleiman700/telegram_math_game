# telegram_math_game
A multi difficulty math game for telegram groups, Built using Php and telegram API

Features:
* Can be used in any group.
* 3 levels of difficulty.
* Supports any amount of players (you can play it alone, with a friend or with the group).
* Control the bot directly from the chat (with commands).

<br>Requirements:
* Hosting (any type).
* MySql database.
* Telegram bot (can be created using BotFather).

<br>How to:
* Upload all files and folders to the host.
* Create mysql database and add the code from (mysql.sql) file.
* Setup database connection in (/inc/db.php).
* Create bot using BotFather
* Put bot token in (/API.php) - ($bot_token = 'bot:token';)
* Set bot WebHook using the following link:<br>
<code>https://api.telegram.org/bot<bot_token_here>/setWebhook?url=https://mysite.com/math_game/API.php</code><br>
Replace <code><bot_token_here></code> with your bot token<br>
Replace <code>https://mysite.com/math_game/API.php</code> with the link to your API.php<br>
You will get a confirmation message (<code>{"ok":true,"result":true,"description":"Webhook was set"}</code>)
* Go to the bot chat and send /math_help
  
  ![image](https://user-images.githubusercontent.com/25286081/146095038-6d5f55e1-a766-4c2c-a612-b2f5afce67fb.png)
  
  <br>
<p>You can add the bot to any group and send /math_help.</p>
<p><strong>Please notice:</strong> that if you want to add the bot to multiple groups make sure to create another API (API.php) and another database.</p>  
  
