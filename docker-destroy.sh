docker rm cachepurger-for-wordpress_wordpress_1 -f
docker rm cachepurger-for-wordpress_db_1 -f
docker ps -a | grep Exit | cut -d ' ' -f 1 | xargs docker rm
docker images | cut -d ' ' -f 1 | xargs docker rmi -f 

