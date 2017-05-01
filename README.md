# Docker Compose workshop

## Required Docker software

Prepare for this workshop by following the [installation instructions](https://docs.docker.com/engine/installation/) for Docker Engine.

Make sure you can run the following commands, showing version numbers higher than or equal to the following:

```
$ docker --version
Docker version 17.03.0-ce, build 60ccb22

$ docker-compose --version
docker-compose version 1.11.2, build dfed245

$ docker-machine --version
docker-machine version 0.10.0, build 76ed2a6
```

## Docker Hub account

If you don't have an account on [hub.docker.com](https://hub.docker.com), create one. Then log in using the command:

```
docker login
```

## VirtualBox

Make sure you have [VirtualBox](https://www.virtualbox.org/) installed on your machine. Next, create a virtual machine that we can use to run Docker containers on by running the following command:

```
docker-machine create --driver virtualbox demo-server
```

To save resources, you can stop and start this machine any time you like by running

```
docker-machine stop demo-server

docker-machine start demo-server
```
