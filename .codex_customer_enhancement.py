from pathlib import Path

flutter_root = Path(r'D:\Projects\Arwaq Al-Eneab\restaurant_manager')
doc_path = flutter_root / 'docs/api/required-api.md'
doc = doc_path.read_text(encoding='utf-8')
doc = doc.replace(
    '- Customer accounts are created through the customer phone/OTP sign-in flow. Do not add a manager endpoint that creates login accounts without phone verification.',
    '- Customers can register through phone/OTP sign-in or be provisioned by a manager. Manager-created accounts must remain unverified until the customer completes phone OTP verification; creation must never issue a customer access token or bypass phone ownership checks.'
)
doc = doc.replace(
    '| `GET` | `/api/v1/manager/customers/stats` | Customer KPIs and comparison values |',
    '| `POST` | `/api/v1/manager/customers` | Create a customer account pending phone verification |\n| `GET` | `/api/v1/manager/customers/stats` | Customer KPIs and comparison values |'
)
doc = doc.replace(
    '| `PATCH` | `/api/v1/manager/customers/{customer}/status` | Block or reactivate a customer account |',
    '| `PATCH` | `/api/v1/manager/customers/{customer}/status` | Block or reactivate a customer account |\n| `PATCH` | `/api/v1/manager/customers/{customer}/verification` | Mark manager review verified/unverified |'
)
insert_before = '## 1. Customer statistics\n'
create_section = '''## Create customer account

`POST /api/v1/manager/customers`

Required: `full_name` and `phone`. `email` is optional. Normalize and uniquely validate the phone number before creating an account; return `422` if another account already uses it. Create the customer with no password, no customer access token, `status=active`, and manager verification pending. The customer must complete the normal phone OTP sign-in flow before the account is treated as phone-verified. The verification flow must persist its phone verification timestamp.

```json
{
  "full_name": "سارة أحمد",
  "phone": "0501234567",
  "email": "sara@example.test"
}
```

Return `201 Created` with the new customer summary, `status`, `phone_verified_at: null`, and `manager_verified_at: null`. Email must be nullable or otherwise handled without creating duplicate placeholder email values.

'''
doc = doc.replace(insert_before, create_section + insert_before, 1)
doc = doc.replace(
    'Allow partial updates to `full_name`, `email`, and `is_vip` only. Validate the email as unique while ignoring the current customer. Do not allow managers to change a customer\'s login phone without a phone-verification flow. Return the updated customer resource. Setting `is_vip` true/false powers the existing VIP segment.',
    'Allow partial updates to `full_name`, `phone`, `email`, and `is_vip`. Validate phone and email uniqueness while ignoring the current customer. If a manager changes the phone, normalize it, clear `phone_verified_at`, revoke existing customer tokens, and require OTP verification for the new number before the customer can sign in. Return the updated customer resource. Setting `is_vip` true/false powers the existing VIP segment.'
)
doc = doc.replace(
    '"email": "khalid@example.test",\n+  "is_vip": true',
    '"phone": "0501234567",\n+  "email": "khalid@example.test",\n+  "is_vip": true'
)
verify_before = '## 7. Export customer list\n'
verify_section = '''## Manager verification

`PATCH /api/v1/manager/customers/{customer}/verification`

This control records that a manager reviewed the customer account. It is separate from phone ownership verification: it must not bypass OTP verification or issue a customer token. Add nullable `manager_verified_at` and `manager_verified_by_staff_user_id` fields to `users`; set both on verification and clear both when verification is revoked.

Request:

```json
{"is_verified": true}
```

Accept a boolean `is_verified`; return the updated verification state, timestamp, and verifying manager ID. Newly created customer accounts start unverified. This verification flag is an administrative review marker, while `phone_verified_at` proves phone ownership through OTP.

'''
doc = doc.replace(verify_before, verify_section + verify_before, 1)
doc = doc.replace('''- Accept the same `search`, `segment`, `status`, and date filters as the customer list.''', '''- Accept the same `search`, `segment`, and `status` filters as the customer list.''')
doc_path.write_text(doc, encoding='utf-8', newline='')

screen_path = flutter_root / 'lib/screens/customers/customers_management_screen.dart'
screen = screen_path.read_text(encoding='utf-8')
screen = screen.replace('''          _buildMetricsKpiGrid(),''', '''          Align(
            alignment: Alignment.centerLeft,
            child: FilledButton.icon(
              onPressed: () => _showCustomerForm(),
              icon: const Icon(Icons.person_add_alt_1),
              label: const Text('إنشاء حساب عميل'),
              style: FilledButton.styleFrom(
                backgroundColor: AppTheme.vine,
                foregroundColor: AppTheme.cream,
              ),
            ),
          ),
          const SizedBox(height: 12),
          _buildMetricsKpiGrid(),''', 1)
screen = screen.replace('''              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.location_on, color: AppTheme.gold),
                title: const Text('العنوان الرئيسي المسجل'),
                subtitle: Text(customer['district']),
              ),''', '''              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.location_on, color: AppTheme.gold),
                title: const Text('العنوان الرئيسي المسجل'),
                subtitle: Text(customer['district']),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  customer['isVerified'] == true ? Icons.verified_user : Icons.pending_outlined,
                  color: customer['isVerified'] == true ? AppTheme.leaf : AppTheme.gold,
                ),
                title: const Text('توثيق الحساب'),
                subtitle: Text(customer['isVerified'] == true ? 'موثق من الإدارة' : 'بانتظار مراجعة الإدارة'),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.of(ctx).pop();
                        _showCustomerForm(customer: customer);
                      },
                      icon: const Icon(Icons.edit_outlined),
                      label: const Text('تعديل البيانات'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () {
                        setState(() {
                          customer['isVerified'] = customer['isVerified'] != true;
                        });
                        Navigator.of(ctx).pop();
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              customer['isVerified'] == true
                                  ? 'تم توثيق الحساب.'
                                  : 'تم إلغاء توثيق الحساب.',
                            ),
                          ),
                        );
                      },
                      icon: Icon(customer['isVerified'] == true ? Icons.verified_outlined : Icons.verified_user_outlined),
                      label: Text(customer['isVerified'] == true ? 'إلغاء التوثيق' : 'توثيق الحساب'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppTheme.vine,
                        foregroundColor: AppTheme.cream,
                      ),
                    ),
                  ),
                ],
              ),''', 1)
needle = '  Widget _buildEmptyState() {'
methods = '''  Future<void> _showCustomerForm({Map<String, dynamic>? customer}) async {
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController(
      text: customer?['name']?.toString() ?? '',
    );
    final phoneController = TextEditingController(
      text: customer?['phone']?.toString() ?? '',
    );
    final emailController = TextEditingController(
      text: customer?['email']?.toString() ?? '',
    );

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.background,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => Directionality(
        textDirection: TextDirection.rtl,
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            20,
            20,
            20 + MediaQuery.viewInsetsOf(sheetContext).bottom,
          ),
          child: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    customer == null ? 'إنشاء حساب عميل' : 'تعديل بيانات العميل',
                    style: const TextStyle(
                      color: AppTheme.chocDeep,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: nameController,
                    decoration: const InputDecoration(labelText: 'الاسم الكامل'),
                    validator: (value) => value == null || value.trim().isEmpty
                        ? 'الاسم مطلوب.'
                        : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'رقم الجوال'),
                    validator: (value) => value == null || value.trim().isEmpty
                        ? 'رقم الجوال مطلوب.'
                        : null,
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(labelText: 'البريد الإلكتروني (اختياري)'),
                  ),
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: () {
                      if (!formKey.currentState!.validate()) return;
                      setState(() {
                        if (customer == null) {
                          _customers.insert(0, {
                            'id': 'CUS-${DateTime.now().millisecondsSinceEpoch}',
                            'name': nameController.text.trim(),
                            'phone': phoneController.text.trim(),
                            'email': emailController.text.trim(),
                            'district': 'غير محدد',
                            'tier': 'عميل جديد',
                            'tierColor': AppTheme.choc,
                            'tierBg': AppTheme.surfaceContainerHigh,
                            'totalOrders': 0,
                            'totalSpent': '0.00 ر.س',
                            'favoriteItem': 'لا توجد طلبات بعد',
                            'lastOrder': 'لا يوجد طلب',
                            'isVerified': false,
                          });
                        } else {
                          customer['name'] = nameController.text.trim();
                          customer['phone'] = phoneController.text.trim();
                          customer['email'] = emailController.text.trim();
                        }
                      });
                      Navigator.of(sheetContext).pop();
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(
                            customer == null
                                ? 'تم إنشاء حساب العميل. يلزم التحقق من رقم الجوال عبر OTP.'
                                : 'تم تحديث بيانات العميل. يلزم إعادة تحقق OTP إذا تغير رقم الجوال.',
                          ),
                        ),
                      );
                    },
                    icon: Icon(customer == null ? Icons.person_add_alt_1 : Icons.save_outlined),
                    label: Text(customer == null ? 'إنشاء الحساب' : 'حفظ التعديلات'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppTheme.vine,
                      foregroundColor: AppTheme.cream,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    nameController.dispose();
    phoneController.dispose();
    emailController.dispose();
  }

'''
screen = screen.replace(needle, methods + needle, 1)
for customer_id in ['CUS-101', 'CUS-102', 'CUS-103', 'CUS-104', 'CUS-105']:
    start = screen.index("'id': '" + customer_id + "'")
    end = screen.index('\n    },', start)
    block = screen[start:end]
    if "'isVerified':" not in block:
        screen = screen[:end] + "\n      'isVerified': " + ('true' if customer_id in ['CUS-101', 'CUS-102'] else 'false') + ',' + screen[end:]
screen_path.write_text(screen, encoding='utf-8', newline='')
