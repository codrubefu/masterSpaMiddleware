## After starting Docker, run the following commands to set up the network and import the database:

```sh
sudo docker network create laravel-net
sudo docker network connect laravel-net laravel-app
sudo docker exec -i sql-server /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'YourStrong!Passw0rd' -C -i /var/opt/mssql/data/spa.sql
```