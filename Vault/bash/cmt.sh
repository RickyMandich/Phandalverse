git add .
git status
nomeCommit=`date "+%Y %m %d %H:%M"`
nomeCommit="aggiornamento "$nomeCommit
git commit -m "$nomeCommit"
clear
git push
sleep 5
clear
