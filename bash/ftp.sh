# bash/ftp.sh
# Itera sui file e carica ciascuno di essi (usando while read per gestire spazi nei nomi)
find . -type f -not -path './.git/*' | while IFS= read -r file; do
    # Salta righe vuote
    [ -z "$file" ] && continue

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