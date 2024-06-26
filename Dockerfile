# Use a imagem oficial do PHP
FROM php:latest

# Adicionar um usuário não privilegiado
RUN useradd -ms /bin/bash phpuser

# Definir um diretório de trabalho dentro do contêiner
WORKDIR /var/www/html

# Copiar o código PHP para dentro do contêiner e definir o proprietário como phpuser
COPY --chown=phpuser:phpuser . /var/www/html

# Expor a porta 8080 para acessar o PHP via HTTP
EXPOSE 8080

# Alterar para o usuário não privilegiado antes de iniciar o servidor PHP
USER phpuser

# Executar o servidor PHP embutido quando o contêiner for iniciado
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www/html"]

