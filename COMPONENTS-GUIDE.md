# Guia de Uso - Componentes Visuais

## 📋 Toast Notifications

### Como Usar

O sistema de toast está disponível globalmente através do objeto `window.toast`.

#### Métodos Disponíveis

```javascript
// Success toast
toast.success('Operação realizada com sucesso!');
toast.success('Usuário criado!', 'Sucesso', 3000); // custom duration

// Error toast
toast.error('Ocorreu um erro ao processar.');
toast.error('Falha na conexão', 'Erro', 5000);

// Warning toast
toast.warning('Atenção: Esta ação não pode ser desfeita.');

// Info toast
toast.info('Novos dados disponíveis.');

// Custom toast
toast.show('Mensagem personalizada', 'info', 'Título', 4000);
```

#### Integração em PHP

```php
// Após uma ação bem-sucedida
echo "<script>toast.success('Usuário criado com sucesso!');</script>";

// Após um erro
echo "<script>toast.error('Erro ao salvar dados.');</script>";
```

---

## 🗺️ Breadcrumbs

### Como Usar

Defina a variável `$breadcrumbs` antes de incluir o layout:

```php
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Usuários', 'url' => '/users'],
    ['label' => 'João Silva', 'url' => '']  // Último item sem URL
];

$title = 'Detalhes do Usuário';
ob_start();
?>

<!-- Seu conteúdo aqui -->

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
```

### Estrutura

- Primeiro item: Geralmente "Dashboard" ou "Home"
- Itens intermediários: Páginas de navegação
- Último item: Página atual (sem link)

---

## 🏷️ Status Badges

### Variantes Disponíveis

#### Status Básicos
```html
<span class="badge badge-success">Ativo</span>
<span class="badge badge-error">Bloqueado</span>
<span class="badge badge-warning">Pendente</span>
<span class="badge badge-info">Em Análise</span>
<span class="badge badge-secondary">Inativo</span>
```

#### Status Semânticos
```html
<span class="badge badge-active">Ativo</span>
<span class="badge badge-inactive">Inativo</span>
<span class="badge badge-online">Online</span>
<span class="badge badge-offline">Offline</span>
<span class="badge badge-blocked">Bloqueado</span>
<span class="badge badge-disabled">Desabilitado</span>
<span class="badge badge-pending">Pendente</span>
```

#### Roles/Funções
```html
<span class="badge badge-admin">Administrador</span>
<span class="badge badge-user">Usuário</span>
<span class="badge badge-guest">Convidado</span>
```

#### Com Ícone
```html
<span class="badge badge-online">
    <svg class="badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="12" cy="12" r="10"/>
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    Online
</span>
```

#### Com Dot Indicator (pulsante)
```html
<span class="badge badge-success badge-dot">Ativo</span>
<span class="badge badge-warning badge-dot">Aguardando</span>
```

### Uso em Tabelas

```php
<table class="data-table">
    <thead>
        <tr>
            <th>Usuário</th>
            <th>Status</th>
            <th>Função</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>João Silva</td>
            <td><span class="badge badge-active badge-dot">Ativo</span></td>
            <td><span class="badge badge-admin">Admin</span></td>
        </tr>
    </tbody>
</table>
```

---

## 🎨 Cores dos Badges

| Badge | Cor | Uso |
|-------|-----|-----|
| `badge-success` | Verde | Sucesso, ativo, online |
| `badge-error` / `badge-danger` | Vermelho | Erro, bloqueado, crítico |
| `badge-warning` | Laranja/Âmbar | Atenção, pendente |
| `badge-info` | Teal | Informação, em análise |
| `badge-primary` | Azul corporativo | Destaque principal |
| `badge-secondary` | Cinza | Neutro, inativo |
| `badge-admin` | Roxo | Administrador |
| `badge-user` | Azul | Usuário comum |
| `badge-guest` | Cinza claro | Convidado |

---

## 📱 Responsividade

Todos os componentes são totalmente responsivos:

- **Toasts**: Ajustam posição em telas pequenas
- **Breadcrumbs**: Quebram linha quando necessário
- **Badges**: Mantêm tamanho legível em qualquer tela

---

## 🌓 Suporte a Temas

Todos os componentes suportam tema claro e escuro automaticamente.

---

## ♿ Acessibilidade

- Toasts incluem `aria-label` nos botões de fechar
- Breadcrumbs usam `aria-label="Breadcrumb"`
- Cores com contraste adequado (WCAG AA)

---

## 📝 Exemplos Práticos

### Exemplo 1: Salvar Usuário com Feedback

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Salvar usuário
        $user->save();
        echo "<script>toast.success('Usuário salvo com sucesso!');</script>";
    } catch (Exception $e) {
        echo "<script>toast.error('Erro ao salvar usuário: " . $e->getMessage() . "');</script>";
    }
}
?>
```

### Exemplo 2: Lista de Usuários com Badges

```php
<table class="data-table">
    <?php foreach ($users as $user): ?>
    <tr>
        <td><?= htmlspecialchars($user['name']) ?></td>
        <td>
            <?php if ($user['status'] === 'active'): ?>
                <span class="badge badge-active badge-dot">Ativo</span>
            <?php else: ?>
                <span class="badge badge-inactive">Inativo</span>
            <?php endif; ?>
        </td>
        <td>
            <span class="badge badge-<?= $user['role'] ?>">
                <?= ucfirst($user['role']) ?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
```

---

## 🔧 Personalização

### Alterar Duração Padrão dos Toasts

Edite `toast.js`:

```javascript
// Linha ~145
success(message, title = 'Sucesso', duration = 4000) {
    // Altere 4000 para o valor desejado em ms
}
```

### Adicionar Novos Tipos de Badge

Edite `toast-breadcrumb-badges.css`:

```css
.badge-custom {
    background: rgba(255, 0, 255, 0.15);
    color: #ff00ff;
    border: 1px solid rgba(255, 0, 255, 0.3);
}
```

---

## 📦 Arquivos Criados

- `/public/assets/css/toast-breadcrumb-badges.css` - Estilos
- `/public/assets/js/toast.js` - Lógica dos toasts
- `/views/examples/components.php` - Página de exemplos
- `/views/layout.php` - Atualizado com breadcrumbs e scripts

---

## ✅ Checklist de Implementação

Para usar em uma nova página:

- [ ] Definir `$breadcrumbs` (opcional)
- [ ] Incluir feedback com toasts após ações
- [ ] Usar badges apropriados em tabelas/listas
- [ ] Testar em tema claro e escuro
- [ ] Verificar responsividade
