# Pusher Setup for Real-Time Messages

You’ve created a Pusher account. Follow these steps to enable real-time message broadcasting.

---

## 1. Get credentials from the Pusher dashboard

1. Go to [dashboard.pusher.com](https://dashboard.pusher.com).
2. Open (or create) your **Channels** app.
3. Go to **App Keys**.
4. Copy:
   - **app_id** → `PUSHER_APP_ID`
   - **key** → `PUSHER_APP_KEY`
   - **secret** → `PUSHER_APP_SECRET`
   - **cluster** (e.g. `us2`, `eu`, `ap1`) → `PUSHER_APP_CLUSTER`

---

## 2. Configure `.env`

In your project `.env`:

1. **Enable Pusher as the broadcaster:**
   ```env
   BROADCAST_CONNECTION=pusher
   ```

2. **Set your Pusher keys** (from step 1):
   ```env
   PUSHER_APP_ID=your_app_id
   PUSHER_APP_KEY=your_key
   PUSHER_APP_SECRET=your_secret
   PUSHER_APP_CLUSTER=us2
   ```

3. **Optional** – leave these empty to use Pusher’s default host:
   ```env
   PUSHER_HOST=
   PUSHER_PORT=443
   PUSHER_SCHEME=https
   ```

Save the file and run:

```bash
php artisan config:clear
```

---

## 3. Run the queue worker

Broadcasting is queued. Events are only sent to Pusher when a queue worker is running.

**Option A – single worker (dev):**
```bash
php artisan queue:work
```

**Option B – Horizon (if you use it):**
```bash
php artisan horizon
```

Keep this process running. If you use `composer run dev`, the queue is already started there.

---

## 4. Verify backend

1. Send a message via the API: `POST /api/v1/conversations/{id}/messages` with `body`.
2. In the Pusher dashboard: **Debug Console** → open your app → you should see events on the private channel `private-conversation.{conversationId}` (event names: `message.sent` / `message.read`).

If nothing appears, check:

- `BROADCAST_CONNECTION=pusher` in `.env`
- Queue worker is running
- No errors in `storage/logs/laravel.log`

---

## 5. Frontend implementation (Angular)

Below are the **real API endpoints**, **request/response shapes**, **messaging flow**, and **Angular + Pusher** setup.

### 5.1 API base URL and auth

- **Base URL:** `https://your-api.com/api/v1` (or your backend origin).
- **Auth:** Send the user’s access token on every request:  
  `Authorization: Bearer <access_token>`.

---

### 5.2 Real endpoints and data

#### Conversations

| Method | Endpoint | Purpose |
|--------|----------|--------|
| GET | `/api/v1/conversations` | List my conversations |
| GET | `/api/v1/conversations?per_page=15` | Pagination (optional) |
| POST | `/api/v1/conversations` | Create or get existing conversation |
| GET | `/api/v1/conversations/{conversationId}` | Get one conversation |

**POST `/api/v1/conversations`** – create or get conversation

- **Request body:**
  ```json
  {
    "user_id": "550e8400-e29b-41d4-a716-446655440000",
    "property_id": "550e8400-e29b-41d4-a716-446655440001"
  }
  ```
  - `user_id` (required): UUID of the other participant.
  - `property_id` (optional): UUID of the property the conversation is about.

- **Response 201:**
  ```json
  {
    "success": true,
    "message": "Conversation retrieved or created successfully",
    "data": {
      "id": "conv-uuid",
      "property": { "id": "...", "title": "...", ... },
      "other_participant": { "id": "...", "name": "...", "email": "...", "photo": "..." },
      "last_message": null,
      "unread_count": 0,
      "last_message_at": null,
      "created_at": "01-15-2025 12:00:00",
      "updated_at": "01-15-2025 12:00:00"
    }
  }
  ```

**GET `/api/v1/conversations`** – list conversations

- **Response 200:**  
  `data` is an array of conversation objects (same shape as the single conversation above), plus `meta` and `links` for pagination.

---

#### Messages

| Method | Endpoint | Purpose |
|--------|----------|--------|
| GET | `/api/v1/conversations/{conversationId}/messages` | Get messages (paginated) |
| GET | `/api/v1/conversations/{conversationId}/messages?per_page=50` | Optional page size |
| POST | `/api/v1/conversations/{conversationId}/messages` | Send a message |
| PUT | `/api/v1/conversations/{conversationId}/messages/read` | Mark all messages in conversation as read |
| PUT | `/api/v1/messages/{messageId}/read` | Mark one message as read |

**POST `/api/v1/conversations/{conversationId}/messages`** – send message

- **Request body:**
  ```json
  {
    "body": "I'm interested in this property. Can we schedule a viewing?"
  }
  ```
  - `body` (required): string, max 5000 chars.

- **Response 201:**
  ```json
  {
    "success": true,
    "message": "Message sent successfully",
    "data": {
      "id": "msg-uuid",
      "conversation_id": "conv-uuid",
      "sender": { "id": "...", "name": "...", "email": "...", "photo": "..." },
      "receiver": { "id": "...", "name": "...", "photo": "..." },
      "body": "I'm interested in this property...",
      "is_read": false,
      "read_at": null,
      "created_at": "01-15-2025 12:00:00",
      "updated_at": "01-15-2025 12:00:00"
    }
  }
  ```

**GET `/api/v1/conversations/{conversationId}/messages`** – list messages

- **Response 200:**
  ```json
  {
    "data": [
      {
        "id": "msg-uuid",
        "conversation_id": "conv-uuid",
        "sender": { "id": "...", "name": "...", "email": "...", "photo": "..." },
        "receiver": { "id": "...", "name": "...", "photo": "..." },
        "body": "...",
        "is_read": true,
        "read_at": "01-15-2025 12:05:00",
        "created_at": "01-15-2025 12:00:00",
        "updated_at": "01-15-2025 12:00:00"
      }
    ],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 1, "from": 1, "to": 1 },
    "links": { "first": "...", "last": "...", "prev": null, "next": null }
  }
  ```

**PUT `/api/v1/conversations/{conversationId}/messages/read`** – mark conversation as read

- **Response 200:**
  ```json
  {
    "success": true,
    "message": "Marked 3 message(s) as read",
    "data": { "conversation_id": "conv-uuid", "messages_marked": 3 }
  }
  ```

---

### 5.3 Broadcast auth (for Pusher private channels)

- **Endpoint:** `POST /api/v1/broadcasting/auth` (or `POST /api/broadcasting/auth` if your API base is `/api`).
- **Headers:** `Authorization: Bearer <access_token>`, `Content-Type: application/json`.
- **Body:** Sent by Pusher: `channel_name=private-conversation.{conversationId}&socket_id=xxx`.
- **Backend:** Returns channel auth payload so the client can subscribe to `private-conversation.{conversationId}`. The frontend only needs to call this with the user’s token; the backend validates the user and conversation.

Use the **full URL** for the auth endpoint in Angular (e.g. `https://your-api.com/api/broadcasting/auth`), and always send the current access token in the `Authorization` header.

---

### 5.4 How messaging logic works

1. **List conversations**  
   `GET /api/v1/conversations` → show inbox (other_participant, last_message, unread_count).

2. **Open or create a conversation**  
   - If starting with another user: `POST /api/v1/conversations` with `user_id` (and optional `property_id`).  
   - Response gives `data.id` = `conversationId`. Use it for messages and Pusher.

3. **Load messages**  
   `GET /api/v1/conversations/{conversationId}/messages` → render messages (e.g. newest at bottom).

4. **Subscribe to real-time for this conversation**  
   - Subscribe to Pusher private channel `private-conversation.{conversationId}`.  
   - Use the broadcast auth endpoint above with the user’s Bearer token.

5. **When the user sends a message**  
   - `POST /api/v1/conversations/{conversationId}/messages` with `{ body }`.  
   - On 201, you can append `data` to the UI immediately (optimistic or from response).  
   - The other participant receives the same message via Pusher (`message.sent`).

6. **When Pusher fires `message.sent`**  
   - Payload: `id`, `conversation_id`, `sender`, `receiver`, `body`, `is_read`, `created_at` (ISO).  
   - If the message is for the current conversation, append it to the list (avoid duplicate by `id`).  
   - If the conversation is in the sidebar, update `last_message` and optionally unread count.

7. **When Pusher fires `message.read`**  
   - Payload: `message_id`, `conversation_id`, `read_by` (id, name), `read_at` (ISO).  
   - Update the message with that `message_id` to `is_read: true`, `read_at`.

8. **Mark as read**  
   - When the user opens a conversation: `PUT /api/v1/conversations/{conversationId}/messages/read`.  
   - Optionally call `PUT /api/v1/messages/{messageId}/read` when a message enters the viewport.  
   - The other user sees read receipts via `message.read` on Pusher.

---

### 5.5 Pusher event payloads (real-time)

**Event name:** `message.sent`

```json
{
  "id": "msg-uuid",
  "conversation_id": "conv-uuid",
  "sender": { "id": "...", "name": "...", "email": "..." },
  "receiver": { "id": "...", "name": "..." },
  "body": "Hello, I'm interested.",
  "is_read": false,
  "created_at": "2025-01-15T12:00:00.000000Z"
}
```

**Event name:** `message.read`

```json
{
  "message_id": "msg-uuid",
  "conversation_id": "conv-uuid",
  "read_by": { "id": "...", "name": "..." },
  "read_at": "2025-01-15T12:05:00.000000Z"
}
```

---

### 5.6 Angular implementation

**1. Install Pusher**

```bash
npm install pusher-js
```

**2. Environment (e.g. `src/environments/environment.ts`)**

```ts
export const environment = {
  production: false,
  apiUrl: 'https://your-api.com/api/v1',
  // Auth endpoint for Pusher private channels (same origin as API)
  broadcastingAuthEndpoint: 'https://your-api.com/api/broadcasting/auth',
  pusher: {
    key: 'your_pusher_key',
    cluster: 'us2',
  },
};
```

**3. Auth: get current access token**

Use your existing auth service that stores the token (e.g. after login). Example:

```ts
// auth.service.ts
getAccessToken(): string | null {
  return this.token; // or from HttpClient interceptor, storage, etc.
}
```

**4. Pusher service (`pusher.service.ts`)**

```ts
import { Injectable } from '@angular/core';
import Pusher from 'pusher-js';
import { environment } from '../environments/environment';
import { AuthService } from './auth.service';

@Injectable({ providedIn: 'root' })
export class PusherService {
  private pusher: Pusher | null = null;
  private channelBindings: Map<string, () => void> = new Map();

  constructor(private auth: AuthService) {}

  private getPusher(): Pusher {
    if (!this.pusher) {
      const token = this.auth.getAccessToken();
      if (!token) throw new Error('Not authenticated');
      this.pusher = new Pusher(environment.pusher.key, {
        cluster: environment.pusher.cluster,
        forceTLS: true,
        channelAuthorization: {
          endpoint: environment.broadcastingAuthEndpoint,
          transport: 'ajax',
          params: {},
          headers: {
            Authorization: `Bearer ${token}`,
          },
        },
      });
    }
    return this.pusher;
  }

  subscribeToConversation(
    conversationId: string,
    onMessageSent: (payload: MessageSentPayload) => void,
    onMessageRead: (payload: MessageReadPayload) => void
  ): () => void {
    const channelName = `private-conversation.${conversationId}`;
    const pusher = this.getPusher();
    const channel = pusher.subscribe(channelName);

    channel.bind('message.sent', onMessageSent);
    channel.bind('message.read', onMessageRead);

    const unbind = () => {
      channel.unbind('message.sent');
      channel.unbind('message.read');
      pusher.unsubscribe(channelName);
    };
    this.channelBindings.set(channelName, unbind);
    return unbind;
  }

  unsubscribeConversation(conversationId: string): void {
    const channelName = `private-conversation.${conversationId}`;
    this.channelBindings.get(channelName)?.();
    this.channelBindings.delete(channelName);
  }
}

export interface MessageSentPayload {
  id: string;
  conversation_id: string;
  sender: { id: string; name: string; email: string };
  receiver: { id: string; name: string };
  body: string;
  is_read: boolean;
  created_at: string;
}

export interface MessageReadPayload {
  message_id: string;
  conversation_id: string;
  read_by: { id: string; name: string };
  read_at: string;
}
```

**5. Conversation / chat component (minimal flow)**

```ts
// In your conversation/chat component
conversationId: string;
messages: Message[] = [];
private unbindPusher: (() => void) | null = null;

ngOnInit(): void {
  // 1) Load messages
  this.loadMessages();
  // 2) Subscribe to real-time
  this.unbindPusher = this.pusherService.subscribeToConversation(
    this.conversationId,
    (payload) => {
      if (this.messages.some(m => m.id === payload.id)) return;
      this.messages.push({
        id: payload.id,
        conversation_id: payload.conversation_id,
        sender: payload.sender,
        receiver: payload.receiver,
        body: payload.body,
        is_read: payload.is_read,
        created_at: payload.created_at,
      } as Message);
    },
    (payload) => {
      const msg = this.messages.find(m => m.id === payload.message_id);
      if (msg) {
        msg.is_read = true;
        msg.read_at = payload.read_at;
      }
    }
  );
  // 3) Mark conversation as read
  this.http.put(
    `${environment.apiUrl}/conversations/${this.conversationId}/messages/read`,
    {}
  ).subscribe();
}

ngOnDestroy(): void {
  this.unbindPusher?.();
  this.pusherService.unsubscribeConversation(this.conversationId);
}

sendMessage(body: string): void {
  this.http.post<{ data: Message }>(
    `${environment.apiUrl}/conversations/${this.conversationId}/messages`,
    { body }
  ).subscribe(res => {
    this.messages.push(res.data);
  });
}

loadMessages(): void {
  this.http.get<{ data: Message[] }>(
    `${environment.apiUrl}/conversations/${this.conversationId}/messages?per_page=50`
  ).subscribe(res => {
    this.messages = res.data;
  });
}
```

**6. CORS and auth endpoint**

- Your API must allow requests from the Angular origin (CORS).
- `POST /api/broadcasting/auth` must accept the Bearer token (same as other API routes). The backend is already set up for `auth:api` on that route.

---

## 6. Frontend (other frameworks – Laravel Echo + Pusher)

If you are **not** using Angular, you can use Laravel Echo + Pusher (e.g. in React/Vue):

```bash
npm install laravel-echo pusher-js
```

Configure Echo with your **API Bearer token** and **broadcast auth endpoint** (full URL, e.g. `https://your-api.com/api/broadcasting/auth`), then subscribe to `private-conversation.{conversationId}` and listen for `message.sent` and `message.read`. The endpoints and event payloads in sections 5.2 and 5.5 still apply.

---

## Checklist

| Step | Action |
|------|--------|
| 1 | Get `app_id`, `key`, `secret`, `cluster` from Pusher dashboard → App Keys |
| 2 | Set `BROADCAST_CONNECTION=pusher` and all `PUSHER_*` vars in backend `.env` |
| 3 | Run `php artisan queue:work` (or Horizon) and keep it running |
| 4 | (Optional) Confirm events in Pusher Debug Console |
| 5 | **Angular:** Install `pusher-js`, add PusherService with channel auth to `/api/broadcasting/auth` and Bearer token, subscribe to `private-conversation.{conversationId}`, bind `message.sent` and `message.read`. Use real endpoints from §5.2 and logic from §5.4. |

After this, new messages and read receipts are broadcast in real time through Pusher.
