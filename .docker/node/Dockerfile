FROM node:22

RUN apt-get update && apt-get install -y \
    build-essential \
    python3 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY package.json package-lock.json ./

RUN npm ci --silent

COPY . .

EXPOSE 5173
CMD ["npm", "run", "dev"]
