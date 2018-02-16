# Define and build the webserver

Define the `webserver` service in `docker-compose.yml`. Describe how it can be built (see `docker/webserver/Dockerfile`):

```yaml
version: '3'

services:
    webserver:
        # define how to build the container image for this service: 
        build: ...
```

To build the container image, run:

```
docker-compose build
```

To start the `webserver` service, run:

```
docker-compose up -d
```

You can analyze services defines in the current project by running:

```
# show the logs of all services
docker-compose logs
```

Or:

```
# show service status:
docker-compose ps
```

> This won't show information from other containers that happen to be running on the same machine, as would be the case when running `docker ps`. 

## Fetching an actual response from the webserver

Currently, there's no easy way to make actual HTTP requests to the `webserver` service. Find out in the [`docker-compose` file documentation](https://docs.docker.com/compose/compose-file/) how you can bind port `80` of the `webserver` container to port `80` of the host. Test it by re-running:

```
docker-compose up -d
```

Now visit our little website at [http://localhost](http://localhost). (You should still see an error about `redis` not being reachable.)

> If something is already listening on port `80` of the host machine, simply try another port.

# Setting up the `redis` dependency

The webserver needs Redis for its visit counter.

Define a second service called "redis". This time, don't add a `build` configuration for it, but provide the name of a ready-made image. Look on [Docker Hub](https://hub.docker.com/) for the official Redis image. Make sure to pick a proper tag (you could choose an `alpine` image for a smaller image size).

Again, run:

```
docker-compose up -d
```

This shouldn't touch the `webserver` service, but only start the `redis` service.

Reload [http://localhost](http://localhost) and you should see the visit count get incremented on every subsequent request.

> Take a look at the code of the website in `web/index.php`. It connects to the `redis` service by providing the host name `redis`. This conveniently corresponds to the name of the service in `docker-compose.yml`.

## Explicitly marking dependencies

Since the `webserver` service depends on the `redis` service, it would be smart to (re)create the `redis` container before (re)creating the `webserver` container. To provide this clue to Docker Compose, you could simply add a `depends_on` configuration key:
 
```
services:
    webserver:
        depends_on:
            - redis
```

As a consequence, whenever the `webserver` service has to be recreated in the future, the `redis` service will also be recreated.

# Code changes

To keep our product manager happy, we'd like to add a random increase to the number of visitors by a random number (which is still believably small). You can use [`INCRBY`](https://redis.io/commands/incrby) to build such a feature.

If you implement this in `web/index.php`, the change won't be immediately visible when reloading the page in the browser. Running `docker-compose up -d` won't help either. It won't even restart the container, but even if it would, it won't help: the code inside the container hasn't been updated yet. Only the code on the host has been changed after all. This means you have to rebuild the container, and *then* restart it:

```
docker-compose build
docker-compose up -d
```

> In some cases, even rebuilding and restarting doesn't help. Then you can:
>
> - Circumvent Docker's build cache by adding `--no-cache` as an option when *build*-ing the image.
> - Circumvent container reuse by adding `--force-recreate` as an option when *up*-ing the service.

## Bind-mounting project files

To keep you from constantly rebuilding images and restarting services to make them reflect code changes, you can bind-mount the project files directly into a service container by adding a [`volumes` configuration key](https://docs.docker.com/compose/compose-file/). 

Take the `web/` directory of the host and mount it as `/var/www/html/` inside the container.

Run `docker-compose up -d` again to make the change effective.

> Note that in this case the bind-mount volume *replaces* the files that we copied to `/var/www/html` as part of the build process.

# Entry points and commands

By default, the `redis` image doesn't really persist its data. Hence, when you stop the `redis` service, the visit counter will be reset. To see this in action, run:

```
docker-compose stop redis
docker-compose rm redis
docker-compose up -d redis
```

Now visit [http://localhost](http://localhost) and you'll see that the old visit counter data has indeed been lost.

> Containers can have entry points and commands. Take a look at the [`Dockerfile` for the `redis` container](https://github.com/docker-library/redis/blob/3f926a47370a19fc88d57d0245823758cbf19b2d/3.2/Dockerfile#L68): it defines an entry point (`ENTRYPOINT`) and a command (`CMD`).

You can easily tweak the `CMD` to make the Redis storage persistent. Add a `command` key to the configuration of the `redis` service and provide as a value for this key: `redis-server --appendonly yes`. Now run:

```
docker-compose up -d redis
```

This should recreate the `redis` service, this time with real persistent storage.

# Data & persistence

If you destroy and start the `redis` service again, like we did before, you will find that the data *still* doesn't get persisted:

```
docker-compose stop redis
docker-compose rm redis
docker-compose up -d redis
```

The problem is: the data gets persisted inside the container, but this is still an isolated environment. You need to bind-mount a location on the host (e.g. `./db`) to the location of the Redis database inside the container (i.e. `/data`). This works in the exact same way as before, when we mounted the `web/` directory to `/var/www/html`.

After you've added the volume to the `redis` service definition, run:

```
docker-compose up -d redis
```

Visit the site, see the counter increase, then destroy and restart the `redis` service:

```
docker-compose stop redis
docker-compose rm redis
docker-compose up -d redis
```

Visit the site again, and you'll find that no data was lost in the process. Don't forget to take a look at the rather interesting file format of the Redis database file in `./db`. 

# Named volumes

To make the solution more "portable" you shouldn't use a bind-mount volume, but a "named volume" instead. This is a volume managed by Docker. The files stored in this volume will be persisted on the host file system, but in a directory managed by Docker. Update the configuration to look like this. Now start and stop the services a few times to verify that the Redis database is indeed persistent.  

```
services:
    ...
    volumes:
        - redis-db:/data
    
volumes:
    redis-db:
```

# Pushing service images

Currently we're building one image (for the `webserver` service). In order to deploy this - theoretically - production-ready image to the `demo-server` virtual machine, we should first push it to an image registry. In order to do so, we need to provide a proper image name for it in `docker-compose.yml`. This name should consist of your Docker Hub username, an image name, and optionally a tag name, e.g.:

```
services:
    webserver:
        image: matthiasnoback/docker-compose-workshop-webserver:latest
        build:
            ...
```

Once the image name is in place, we need to re-build it:

```
docker-compose build webserver
```

This will re-build the image (probably entirely from cache), and tag it appropriately.

Next, we can run:

```
docker-compose push
```

This will make the image publicly available on [Docker Hub](http://hub.docker.com/). You should see the image appear on the dashboard.

> Releasing new versions of your service entails the same two steps over and over again: `build` and `push`.

# Deploying services on a single node

Once you have pushed your custom image to Docker Hub, other servers can start using them.

## Step 1 (using VirtualBox on Linux, Mac or Windows Home Edition)

If you've followed the instructions in the [`README.md`](../README.md) file of this project, you already have a virtual machine called `demo-server` up and running. To see if this is the case, run:

```
docker-machine ls
```

You should see the `demo-server` in the list that gets printed on the screen. Copy the IP address of the machine and try opening it in your web browser. You should get an error saying that the browser couldn't connect to the server.

Let's start our `webserver` service (and `redis` of course) on the VM. We can do so by making the Docker client of the host interact with the Docker daemon that runs on the VM. To do so, run:

```
eval $(docker-machine env demo-server)
```

Now run:

```
docker ps
```

You should see an empty list. The effect of running the `eval` command previously is that the Docker client is now talking with the VM, on which no containers are currently running. We could manually start containers using `docker` commands, but we could easily use our carefully crafted `docker-compose.yml` too. First, we let the `demo-server` pull the relevant images:

```
docker-compose pull
```

Before we start the services, we need to make sure that we don't use any bind-mount volumes (e.g. `./db:data`). For now, comment those out in `docker-compose.yml`. Then, run:

```
docker-compose up -d
```

Afterwards run `docker ps` (or `docker-compose ps`) again to see that the requested services are running. Also, refresh your browser, and you should see that the website is now functioning properly!

Note that you were never forced to `ssh` into the VM or anything. Everything could be handled remotely, through use of the Docker client.

Finally, to bring back the focus of the Docker client to the host machine, run:

```
eval $(docker-machine env -u)
```

## Step 1 (using Play With Docker for Windows Professional Edition or if you like it better than Virtualbox)

Go to [labs.play-with-docker.com](http://labs.play-with-docker.com/). Click "Add new instance".

First, make sure your `docker-compose.yml` is ready to be used for deploying services remotely:

1. Comment out all the lines describing local bind-mount volumes (e.g. `./db:/data`).
2. Make sure you only use double quotes in your `docker-compose.yml` file. 

Now in the PWD terminal, type:

```
echo '
``` 

Then paste the contents of your `docker-compose.yml` file. Then add:

```
` > docker-compose.yml
```

Alternatively you could run `vi docker-compose.yml` and paste it there. However, you'd first need to go into *paste mode* (I know, right???) by typing in: `:set paste`.

Try `cat docker-compose.yml` and verify that it looks good.

Back on the terminal you can run:

```
docker-compose pull
```

This pulls in all the required images. Finally, you can start the services by running:

```
docker-compose up -d --no-build
```

Verify that everything works by running:

```
docker-compose ps
```

Somewhere on the screen a link will pop up with the port number which you can use to visit the website and see the visitor counter in action.

## Step 2: Taking care of persistence

Try shutting down the services (like with a reboot of the system):

```
docker-compose down
```

Then start the services again:

```
docker-compose up -d
```

If you visit the website again, you'll notice that the visitor counter has been reset. In order to make the data persistent, we need to bind-mount a volume from the host machine. Using Virtualbox you could use `/home/docker` and mount it as `/data` inside the `redis` container. Using Play With Docker you could use `/home`.

Restart the services several times and see if their data would survive an outage.

# Using separate configuration files

A production container isn't the same as a development container. Currently the two are merged in one `docker-compose.yml`, but it would be a good idea to separate them. Rename `docker-compose.yml` to `docker-compose.prod.yml` and create another file, `docker-compose.dev.yml`, which contains some additions that are only useful when developing (e.g. build configuration, bind-mount of project files, etc.) 

Whenever you're running a `docker-compose` command you should now add which config file(s) you'd like to use, e.g.

```
docker-compose -f docker-compose.prod.yml pull 
docker-compose -f docker-compose.prod.yml up -d --no-build --force-recreate
 
docker-compose -f docker-compose.prod.yml -f docker-compose.dev.yml build
docker-compose -f docker-compose.prod.yml -f docker-compose.dev.yml push
```

> Of course, you can simplify these commands by putting the first part of these commands in a Bash variable, or writing build scripts or Makefiles for them.

# Add a network configuration

Just like you would run `docker network create website` and explicitly add containers to this network by using `docker run --network website ...`, you can also configure networks in `docker-compose.yml` and add services to them. `docker-compose` will automatically create and remove these networks for you. Take a look at the [documentation](https://docs.docker.com/compose/compose-file/#networks) to figure out how to define the `website` network in your `docker-compose.yml` and add the services to this network.
