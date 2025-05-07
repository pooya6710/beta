<?php


if (!$match) exit();

?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CSS Grid Ribbon Layout</title>

        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Bungee|Bungee+Inline" rel="stylesheet">

        <!-- Meyer Reset CSS -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.min.css">

        <style>
            body {
                background-color: #33658A;
                color: #1A2830;
                font-family: 'Bungee', cursive;
            }

            h1 {
                margin: auto;
                margin-top: 100px;
                display: grid;
                width: 300px;
                grid-auto-rows: 60px;
                font-size: 3em;
            }

            h1 > span {
                background-color: #C60F0F;
                line-height: 60px;
                text-align: center;
                transform: skewY(11deg);
            }

            h1 > span:nth-child(2n) {
                background-color: #FE4E00;
                transform: skewY(-11deg);
                z-index: 1;
            }
        </style>
    </head>
    <body>
    <?php if ($match['status']  != 'draw' && $match['winner_id']){?>
        <h1 data-splitting>PLAYER <?= $match['player'.$match['winner_id'].'_name'] ?> WON</h1>
    <?php }else{ ?>
    <h1 data-splitting>THE MATCH ENDED AS A DRAW</h1>
    <?php } ?>
    <!-- Splitting.js Library -->
    <script src="https://unpkg.com/splitting/dist/splitting.min.js"></script>
    <script>
        const results = Splitting();
        results[0].el.insertBefore(document.createElement("span"), results[0].el.children[0]);
        results[0].el.appendChild(document.createElement("span"));
    </script>
    </body>
    </html>


<?php
exit();
