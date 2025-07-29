# Workflow: User

## Arrived / Shift Apply Enabled

``` mermaid
graph LR
  start(Start);
  final(End);
  dest_info_desk[GO to Info Desk];
  locataion_info_desk[Info Desk check-in];
  action_set_arrive[Set ARRIVE Flag];
  action_auto_arrive[Auto ARRIVE Flag];
  is_staff{Is STAFF?};
  flag_shifts_enabled(Can apply to Shifts);

  start --> is_staff;
  is_staff -->|Yes| action_auto_arrive;
  is_staff -->|No| dest_info_desk;
  dest_info_desk --> locataion_info_desk;
  locataion_info_desk --> action_set_arrive;

  action_set_arrive --> flag_shifts_enabled;
  action_auto_arrive --> flag_shifts_enabled;
  flag_shifts_enabled --> final;
```


## Onboarding

1. login with IDP
1. Get user consent to use the system
1. Get user information (Nickname blocked - only admin can change)
1. Offer to connect telegram



Workflow update:

## Onboarding Attenddee

- Critter needs to go to the Info Desk first
- Info Desk > Set the ARRIVED flag

- Info Desk > Orange / Blue ribbon >> Includes Safety Training >> Add to the list of tracking

-> STAFF > Automatic arrival


Help Desk Dashboard
>> Show the critters
>> Trainings done
>> Shifts
>> Goodies collected / to collect

>> Add support for badcode Scanner > find by Badge Number >> Hardware ordered

Add to onboarding:
>> Don't do bullshit
>> You are not STAFF, just a helper to our staff