import { CommonModule } from '@angular/common';
import { HttpClient, HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';

type InvoiceStatus = 'pending' | 'paid' | 'overdue';
type InvoiceStatusFilter = InvoiceStatus | 'all';
type AuthMode = 'register' | 'login';

type BillingPlanKey = 'starter' | 'growth' | 'scale';

interface PaginatedResponse<T> {
  data: T[];
}

interface DashboardResponse {
  outstanding_cents: number;
  overdue_cents: number;
  recovered_this_month_cents: number;
  open_invoice_count: number;
  paid_invoice_count: number;
  reminders_due_now: number;
  api_usage: {
    used_this_month: number;
    monthly_quota: number;
  };
}

interface ClientRecord {
  id: number;
  name: string;
  email: string | null;
  company: string | null;
  timezone: string;
}

interface ReminderRecord {
  sequence: number;
  status: 'pending' | 'sent' | 'skipped';
  scheduled_for: string | null;
  sent_at: string | null;
}

interface InvoiceRecord {
  id: number;
  invoice_number: string;
  status: InvoiceStatus;
  amount: number;
  amount_cents: number;
  currency: string;
  issued_at: string;
  due_at: string;
  paid_at: string | null;
  payment_url: string | null;
  late_fee_percent: number;
  client: {
    id: number;
    name: string;
    email: string | null;
  };
  reminders: ReminderRecord[];
}

interface UserApiKeySummary {
  id: number;
  name: string;
  owner_email: string | null;
  plan: string;
  monthly_quota: number;
  used_this_month: number;
  active: boolean;
}

interface UserProfile {
  id: number;
  name: string;
  email: string;
  billing_plan: string;
  subscription_status: string;
  subscription_ends_at: string | null;
  api_keys: UserApiKeySummary[];
}

interface AuthResponse {
  message: string;
  auth_token: string;
  default_api_key: string | null;
  user: UserProfile;
}

interface RegisterOtpResponse {
  message: string;
  otp_sent: boolean;
  email: string;
}

interface MeResponse {
  user: UserProfile;
}

interface CheckoutSessionResponse {
  checkout_url: string;
  session_id: string;
  plan: string;
}

interface BillingPlanCard {
  key: BillingPlanKey;
  label: string;
  price: string;
  quota: string;
  summary: string;
}

@Component({
  selector: 'app-root',
  imports: [CommonModule, FormsModule],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
  private readonly http = inject(HttpClient);

  protected authMode: AuthMode = 'register';
  protected readonly billingPlans: BillingPlanCard[] = [
    {
      key: 'starter',
      label: 'Starter',
      price: '$9/mo (Beta)',
      quota: '5,000 API calls',
      summary: 'Great for solo consultants and freelancers.'
    },
    {
      key: 'growth',
      label: 'Growth',
      price: '$19/mo (Beta)',
      quota: '25,000 API calls',
      summary: 'Best for agency teams with recurring invoices.'
    },
    {
      key: 'scale',
      label: 'Scale',
      price: '$49/mo (Beta)',
      quota: '100,000 API calls',
      summary: 'For high-volume finance ops and multi-client usage.'
    }
  ];

  protected authForm = {
    name: '',
    email: '',
    password: '',
    otp: ''
  };

  protected apiKeyInput = localStorage.getItem('recoverflow.apiKey') ?? '';
  protected authToken = localStorage.getItem('recoverflow.authToken') ?? '';
  protected statusFilter: InvoiceStatusFilter = 'all';

  protected readonly loading = signal(false);
  protected readonly loaded = signal(false);
  protected readonly errorMessage = signal('');
  protected readonly successMessage = signal('');
  protected readonly dashboard = signal<DashboardResponse | null>(null);
  protected readonly clients = signal<ClientRecord[]>([]);
  protected readonly invoices = signal<InvoiceRecord[]>([]);
  protected readonly userProfile = signal<UserProfile | null>(null);
  protected readonly registrationOtpSent = signal(false);

  protected clientForm = {
    name: '',
    email: '',
    company: '',
    timezone: 'America/New_York',
    notes: ''
  };

  protected invoiceForm = {
    client_id: 0,
    invoice_number: '',
    amount: 1500,
    currency: 'USD',
    issued_at: this.todayDate(),
    due_at: this.futureDate(14),
    payment_url: '',
    late_fee_percent: 2.5
  };

  protected readonly canRefresh = computed(() => this.apiKeyInput.trim().length > 0);
  protected readonly isAuthenticated = computed(() => this.authToken.trim().length > 0);

  public constructor() {
    if (this.authToken.trim().length > 0) {
      void this.loadCurrentUser();
    }
  }

  protected setAuthMode(mode: AuthMode): void {
    this.authMode = mode;
    this.registrationOtpSent.set(false);
    this.authForm.otp = '';
    this.clearMessages();
  }

  protected async submitAuth(): Promise<void> {
    this.loading.set(true);
    this.clearMessages();

    try {
      if (this.authMode === 'register') {
        if (this.registrationOtpSent()) {
          await this.verifyRegistrationOtpAndCreateAccount();
        } else {
          await this.requestRegistrationOtp();
        }
      } else {
        await this.loginUser();
      }
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async logout(): Promise<void> {
    if (!this.isAuthenticated()) {
      return;
    }

    this.loading.set(true);
    this.clearMessages();

    try {
      await this.postAuthed('/auth/logout', {});
      this.authToken = '';
      this.userProfile.set(null);
      localStorage.removeItem('recoverflow.authToken');
      this.successMessage.set('Logged out successfully.');
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async connectAndLoad(): Promise<void> {
    if (!this.canRefresh()) {
      this.setError('Enter an API key first.');
      return;
    }

    localStorage.setItem('recoverflow.apiKey', this.apiKeyInput.trim());
    await this.refreshAll();
  }

  protected async refreshAll(): Promise<void> {
    this.loading.set(true);
    this.clearMessages();

    try {
      await Promise.all([this.loadDashboard(), this.loadClients(), this.loadInvoices()]);
      this.loaded.set(true);
      this.successMessage.set('Data refreshed successfully.');
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async createClient(): Promise<void> {
    this.loading.set(true);
    this.clearMessages();

    try {
      const createdClient = await this.postWithApiKey<ClientRecord>('/clients', this.clientForm);

      this.clientForm = {
        ...this.clientForm,
        name: '',
        email: '',
        company: '',
        notes: ''
      };

      await Promise.all([this.loadClients(), this.loadDashboard()]);

      if (this.invoiceForm.client_id === 0) {
        this.invoiceForm = {
          ...this.invoiceForm,
          client_id: createdClient.id
        };
      }

      this.successMessage.set('Client created successfully.');
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async createInvoice(): Promise<void> {
    this.loading.set(true);
    this.clearMessages();

    if (this.invoiceForm.client_id <= 0) {
      this.setError('Select a client before creating an invoice.');
      this.loading.set(false);
      return;
    }

    try {
      await this.postWithApiKey<InvoiceRecord>('/invoices', this.invoiceForm);
      this.invoiceForm = {
        ...this.invoiceForm,
        invoice_number: '',
        amount: 1500,
        issued_at: this.todayDate(),
        due_at: this.futureDate(14),
        payment_url: ''
      };

      await Promise.all([this.loadInvoices(), this.loadDashboard(), this.loadClients()]);
      this.successMessage.set('Invoice created with automated reminders.');
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async markInvoicePaid(invoiceId: number): Promise<void> {
    this.loading.set(true);
    this.clearMessages();

    try {
      await this.postWithApiKey(`/invoices/${invoiceId}/mark-paid`, {});
      await Promise.all([this.loadInvoices(), this.loadDashboard()]);
      this.successMessage.set('Invoice marked paid. Pending reminders were skipped.');
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async onStatusFilterChange(nextStatus: InvoiceStatusFilter): Promise<void> {
    this.statusFilter = nextStatus;

    if (this.loaded()) {
      await this.loadInvoices();
    }
  }

  protected async startCheckout(plan: BillingPlanKey): Promise<void> {
    if (!this.isAuthenticated()) {
      this.setError('Sign in first to start a billing checkout session.');
      return;
    }

    this.loading.set(true);
    this.clearMessages();

    try {
      const response = await this.postAuthed<CheckoutSessionResponse>('/billing/checkout-session', {
        plan
      });

      window.open(response.checkout_url, '_blank', 'noopener');
      this.successMessage.set(`Stripe checkout opened for ${plan} plan.`);
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  protected async resendRegistrationOtp(): Promise<void> {
    if (this.authMode !== 'register') {
      return;
    }

    this.loading.set(true);
    this.clearMessages();

    try {
      await this.requestRegistrationOtp();
    } catch (error) {
      this.setError(this.formatError(error));
    } finally {
      this.loading.set(false);
    }
  }

  private async requestRegistrationOtp(): Promise<void> {
    const response = await this.postPublic<RegisterOtpResponse>('/auth/register', {
      name: this.authForm.name,
      email: this.authForm.email,
      password: this.authForm.password
    });

    this.registrationOtpSent.set(response.otp_sent);
    this.successMessage.set(response.message);
  }

  private async verifyRegistrationOtpAndCreateAccount(): Promise<void> {
    const response = await this.postPublic<AuthResponse>('/auth/register/verify-otp', {
      email: this.authForm.email,
      otp: this.authForm.otp
    });

    this.onAuthSuccess(response, 'Account created successfully.');
  }

  private async loginUser(): Promise<void> {
    const response = await this.postPublic<AuthResponse>('/auth/login', {
      email: this.authForm.email,
      password: this.authForm.password
    });

    this.onAuthSuccess(response, 'Logged in successfully.');
  }

  private onAuthSuccess(response: AuthResponse, message: string): void {
    this.authToken = response.auth_token;
    localStorage.setItem('recoverflow.authToken', response.auth_token);
    this.userProfile.set(response.user);

    if (typeof response.default_api_key === 'string' && response.default_api_key.length > 0) {
      this.apiKeyInput = response.default_api_key;
      localStorage.setItem('recoverflow.apiKey', response.default_api_key);
    }

    this.authForm = {
      name: '',
      email: '',
      password: '',
      otp: ''
    };
    this.registrationOtpSent.set(false);

    this.successMessage.set(message);
  }

  private async loadCurrentUser(): Promise<void> {
    try {
      const response = await this.getAuthed<MeResponse>('/auth/me');
      this.userProfile.set(response.user);
    } catch {
      this.authToken = '';
      localStorage.removeItem('recoverflow.authToken');
      this.userProfile.set(null);
    }
  }

  private async loadDashboard(): Promise<void> {
    const dashboard = await this.getWithApiKey<DashboardResponse>('/dashboard');
    this.dashboard.set(dashboard);
  }

  private async loadClients(): Promise<void> {
    const response = await this.getWithApiKey<PaginatedResponse<ClientRecord>>('/clients?per_page=50');
    this.clients.set(response.data ?? []);
  }

  private async loadInvoices(): Promise<void> {
    const query = new URLSearchParams({ per_page: '50' });

    if (this.statusFilter !== 'all') {
      query.set('status', this.statusFilter);
    }

    const response = await this.getWithApiKey<PaginatedResponse<InvoiceRecord>>(`/invoices?${query.toString()}`);
    this.invoices.set(response.data ?? []);
  }

  private async getPublic<T>(path: string): Promise<T> {
    return await firstValueFrom(this.http.get<T>(`/api/v1${path}`));
  }

  private async postPublic<T = unknown>(path: string, payload: unknown): Promise<T> {
    return await firstValueFrom(this.http.post<T>(`/api/v1${path}`, payload));
  }

  private async getWithApiKey<T>(path: string): Promise<T> {
    return await firstValueFrom(this.http.get<T>(`/api/v1${path}`, this.apiKeyRequestOptions()));
  }

  private async postWithApiKey<T = unknown>(path: string, payload: unknown): Promise<T> {
    return await firstValueFrom(this.http.post<T>(`/api/v1${path}`, payload, this.apiKeyRequestOptions()));
  }

  private async getAuthed<T>(path: string): Promise<T> {
    return await firstValueFrom(this.http.get<T>(`/api/v1${path}`, this.authRequestOptions()));
  }

  private async postAuthed<T = unknown>(path: string, payload: unknown): Promise<T> {
    return await firstValueFrom(this.http.post<T>(`/api/v1${path}`, payload, this.authRequestOptions()));
  }

  private apiKeyRequestOptions(): { headers: HttpHeaders } {
    return {
      headers: new HttpHeaders({
        'X-Api-Key': this.apiKeyInput.trim()
      })
    };
  }

  private authRequestOptions(): { headers: HttpHeaders } {
    return {
      headers: new HttpHeaders({
        Authorization: `Bearer ${this.authToken.trim()}`
      })
    };
  }

  private formatError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      const serverMessage = error.error?.message;

      if (typeof serverMessage === 'string' && serverMessage.length > 0) {
        return serverMessage;
      }

      return `${error.status}: ${error.statusText || 'Request failed'}`;
    }

    return 'Something went wrong. Please retry.';
  }

  private setError(message: string): void {
    this.errorMessage.set(message);
    this.successMessage.set('');
  }

  private clearMessages(): void {
    this.errorMessage.set('');
    this.successMessage.set('');
  }

  private todayDate(): string {
    return new Date().toISOString().slice(0, 10);
  }

  private futureDate(daysFromNow: number): string {
    const future = new Date();
    future.setDate(future.getDate() + daysFromNow);

    return future.toISOString().slice(0, 10);
  }
}
