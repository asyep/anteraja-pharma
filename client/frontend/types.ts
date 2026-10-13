export type Role = "admin" | "courier" | "buyer";
export interface User {
  id: number;
  name: string;
  email?: string;
  role: Role;
}
export interface Inspection {
  id: number;
  packaging: boolean;
  seal: boolean;
  label: boolean;
  passed: boolean;
  note: string;
  temperature: number | null;
  user?: User;
  created_at: string;
}
export interface Assignment {
  id: number;
  courier_id: number;
  courier?: User;
  status: string;
  reason: string;
  created_at: string;
}
export interface Decision {
  id: number;
  action: string;
  reason: string;
  user?: User;
  created_at: string;
}
export interface Issue {
  id: number;
  type: string;
  description: string;
  status: string;
  user?: User;
  decisions: Decision[];
  created_at: string;
}
export interface AuditEvent {
  id: number;
  actor_name: string;
  type: string;
  title: string;
  reason: string;
  created_at: string;
  to_status: string;
}
export interface Approval {
  id: number;
  decision: string;
  reason: string;
  user?: User;
  created_at: string;
}
export interface Shipment {
  id: number;
  tracking_number: string;
  buyer_id: number;
  inspector_id: number;
  version: number;
  sender_name: string;
  recipient_name: string;
  recipient_phone: string;
  address: string;
  origin_city: string;
  destination_city: string;
  item_summary: string;
  package_count: number;
  handling_instructions: string;
  temperature_profile: string;
  temperature_min: number | null;
  temperature_max: number | null;
  scheduled_at: string;
  status: string;
  last_location: string;
  recipient_ready: boolean | null;
  buyer?: User;
  inspector?: User;
  inspections?: Inspection[];
  approvals?: Approval[];
  assignments?: Assignment[];
  issues?: Issue[];
  events?: AuditEvent[];
  created_at: string;
}
export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}
export interface OutboundMessage {
  id: number;
  shipment_id: number | null;
  subject: string;
  body: string;
  audience: string;
  status: string;
  action_url: string;
  attempts: number;
  last_error: string | null;
  sent_at: string | null;
  created_at: string;
  provider_message_id: string | null;
}
export interface Report {
  id: number;
  report_date: string;
  revision: number;
  summary: {
    total: number;
    by_status: Record<string, number>;
    issues_today: number;
    events_today: number;
    blocked_dispatch_today: number;
  };
  created_at: string;
}
