# Use uma imagem base, como uma distribuição Linux leve
FROM alpine:latest

# Defina um diretório de trabalho na imagem
WORKDIR /app

# Copie o script que será executado no container
COPY loop.sh .

# Defina permissões para o script
RUN chmod +x loop.sh

# Configure um usuário não privilegiado
RUN adduser -D appuser
USER appuser

# Defina o comando de entrada (entrypoint)
ENTRYPOINT ["./loop.sh"]
