# Examples with the commands

## Send a welcome to internship email

```
bin/console app:send-intern-email intern@example.com "Joe Intern" --occasion welcome
```

## Email notify about new feedback from the trainer

```
bin/console app:send-intern-email intern@example.com "Joe Intern" --occasion feedback
```

## Send an email with course notes for several chapters

```
bin/console app:send-course-notes-emails php-basics symfony-install
```

And if the messenger component is installed and configured so that emails
sent using the Mailer are queued instead of directly sent, then your mail
sending message is not in the queue, and you need to run the messenger consume
command in order to send it:

```
php bin/console messenger:consume async
```

# Setting up the database tables to use for messenger queues

In Symfony, when you use **Messenger with Doctrine transport**, you need a database table
(usually called `messenger_messages`). Symfony can generate the migration for you.

Before starting to use doctrine to store messages in tables, you need to run
`composer require symfony/doctrine-messenger` to install Doctrine transport.

### Steps

1. **Configure Doctrine transport in `messenger.yaml`:**

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
```

And in `.env`:

```env
MESSENGER_TRANSPORT_DSN=doctrine://default
```

---

2. **Generate the transport table schema:**

Symfony has a built-in command that creates the SQL for you:

```bash
php bin/console messenger:setup-transports
```

* This creates the `messenger_messages` table directly in the DB.

If you want it **as a Doctrine migration** instead of direct execution:

```bash
php bin/console doctrine:migrations:diff
```

It will detect the missing `messenger_messages` table and generate a migration class.

---

3. **Apply the migration:**

```bash
php bin/console doctrine:migrations:migrate
```

---

### Example `messenger_messages` schema (auto-generated)

```sql
CREATE TABLE messenger_messages (
    id BIGINT AUTO_INCREMENT NOT NULL,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME DEFAULT NULL,
    PRIMARY KEY(id)
);
CREATE INDEX IDX_QUEUE_NAME ON messenger_messages (queue_name);
CREATE INDEX IDX_AVAILABLE_AT ON messenger_messages (available_at);
CREATE INDEX IDX_DELIVERED_AT ON messenger_messages (delivered_at);
```
