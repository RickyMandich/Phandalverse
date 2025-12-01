# Ottieni l'elenco dei file modificati nell'ultimo commit
changedFiles=$(find . -type f -not -path './.git/*')

# Itera sui file modificati e carica ciascuno di essi
for file in $changedFiles; do
    # Costruisci il percorso FTP per il file
    relativePath=$(dirname "$file" | sed 's/^\.\///')
    fileName=$(basename "$file")
    ftpRequest="ftp://phandalverse:Minecraft35%3F@ftp.phandalverse.altervista.org:21/$relativePath/$fileName"

    # Esegui il comando curl per caricare il file
    curlCommand="curl -T \"$file\" \"$ftpRequest\" --ftp-pasv --ftp-create-dirs"
    echo -e "$curlCommand"
    eval "$curlCommand"
    echo "$relativePath/$fileName caricato con successo."
done