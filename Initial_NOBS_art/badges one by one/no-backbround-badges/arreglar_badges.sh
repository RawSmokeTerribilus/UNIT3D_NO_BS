#!/bin/bash

# 1. Definimos la lista de nombres deseados (puedes pegarlos todos aquí)
nombres=(
"100Comments.png" "50Comments.png" "Filled50Requests.png"
"100Posts.png" "50Posts.png" "Filled75Requests.png"
"100Uploads.png" "50Uploads.png" "FirstComment.png"
"10Comments.png" "600Comments.png" "FirstPost.png"
"200Comments.png" "600Posts.png" "FirstUpload.png"
"200Posts.png" "600Uploads.png" "UserUploaded1000Subtitles.png"
"200Uploads.png" "700Comments.png" "UserUploaded100Subtitles.png"
"25Posts.png" "700Posts.png" "UserUploaded200Subtitles.png"
"25Uploads.png" "700Uploads.png" "UserUploaded25Subtitles.png"
"300Comments.png" "800Comments.png" "UserUploaded300Subtitles.png"
"300Posts.png" "800Posts.png" "UserUploaded400Subtitles.png"
"300Uploads.png" "800Uploads.png" "UserUploaded500Subtitles.png"
"400Comments.png" "900Comments.png" "UserUploaded500Subtitles.png"
"400Posts.png" "900Posts.png" "UserUploaded600Subtitles.png"
"400Uploads.png" "900Uploads.png" "UserUploaded700Subtitles.png"
"500Comments.png" "UserUploaded800Subtitles.png"
"500Posts.png" "Filled100Requests.png" "UserUploaded900Subtitles.png"
"500Uploads.png" "Filled25Requests.png" "UserUploadedFirstSubtitle.png"
)

# 2. Obtenemos los archivos *-badge.png ordenados numéricamente de mayor a menor
# Usamos 'sort -V' para versiones/números y 'tac' para invertir el orden
archivos=($(ls *-badge.png | sort -V | tac))

# 3. Emparejamos y renombramos
i=0
for f in "${archivos[@]}"; do
    if [ $i -lt ${#nombres[@]} ]; then
        echo "Renombrando $f a ${nombres[$i]}..."
        mv "$f" "${nombres[$i]}"
        ((i++))
    else
        echo "Se acabaron los nombres. Saltando $f..."
    fi
done

echo "¡Hecho!"
