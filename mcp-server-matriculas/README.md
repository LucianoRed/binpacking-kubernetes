# Sistema de Matrículas (MCP Server + PHP Demo)

Este projeto demonstra um sistema de matrículas simples com interface Web em PHP e integração via MCP (Model Context Protocol).

## Estrutura

- `public/index.php`: Interface Web para listar, buscar e matricular alunos.
- `src/index.js`: Servidor MCP em Node.js que expõe ferramentas para listar, buscar e cadastrar alunos.
- `data/students.json`: Armazenamento de dados em arquivo JSON.

## Como Executar

### Usando Docker

1. **Construir a imagem:**
   ```bash
   docker build -t mcp-matriculas .
   ```

2. **Rodar o container (Interface Web):**
   ```bash
   docker run -d -p 8080:80 --name mcp-matriculas-app mcp-matriculas
   ```
   Acesse http://localhost:8080 para ver a interface web.

3. **Usar o Servidor MCP:**
   
   Configure seu cliente MCP (ex: Claude Desktop ou IDE) para usar o seguinte comando:
   ```bash
   docker exec -i mcp-matriculas-app node src/index.js
   ```
   
   Isso permite que o agente de IA interaja com o *mesmo* arquivo de dados que a interface web, permitindo que alterações feitas por um lado sejam visíveis no outro.

## Ferramentas MCP Disponíveis

- `list_students`: Lista todos os alunos cadastrados.
- `search_student`: Busca alunos por nome.
   - Argumento: `query` (string)
- `register_student`: Matricula um novo aluno.
   - Argumentos: `name` (string), `dob` (YYYY-MM-DD), `year` (string)
