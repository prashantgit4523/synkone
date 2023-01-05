import React, {
  forwardRef,
  Fragment,
  useEffect,
  useImperativeHandle,
  useState,
  useRef
} from "react";
import Select, { components } from "react-select";
import Datetime from "react-datetime";
import { Inertia } from "@inertiajs/inertia";
import { usePage } from "@inertiajs/inertia-react";
import { useSelector } from "react-redux";
import "react-datetime/css/react-datetime.css";
import TimezoneList from "../../../../../utils/timezone-list";
import { useForm, Controller } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { subDays } from "date-fns";
import * as yup from "yup";
import moment from "moment-timezone";
import LoadingButton from '../../../../../common/loading-button/LoadingButton';
import UsersAndGroups from "./UsersAndGroups";

const NoOptionsMessage = props => {
  return (
    <components.NoOptionsMessage {...props}>
      <span className="custom-css-class">Create a new group using the button first.</span> 
    </components.NoOptionsMessage>
  );
};
    

const schema = yup
  .object({
    name: yup.string().required('Name is a required field'),
    policies: yup.array().required('Policy(ies) is a required field').min(1, 'Policy(ies) field must have at least 1 items'),
    groups: yup.array().required('Audience is a required field').min(1,'Audience field must have at least 1 items'),
    launch_date: yup.string().required('Launch date is a required field'),
    due_date: yup.string().required('Due date is a required field'),
    auto_enroll_users: yup.string().required('Auto-enroll user is a required field'),
    timezone: yup.string().required(),
  })
  .required();

function AddAwarenessCampaignForm(props, ref) {
  const { policies, groups, groupUsers,controlId,projectId,ssoIsEnabled,overlayAdded = false } = props;
  const {
    setIsFormSubmitting,
    setShowCampaignAddModal,
  } = props;
  const appDataScope = useSelector(
    (state) => state.appDataScope.selectedDataScope.value
  );
  const { globalSetting, errors: serverSideValErrors } = usePage().props;
  const {
    reset,
    register,
    trigger,
    getValues,
    control,
    setValue,
    formState: { errors },
    setError,
  } = useForm({
    resolver: yupResolver(schema),
    reValidateMode: "onChange",
    mode: "onChange",
  });

  const dueDate = new Date();
  dueDate.setDate(dueDate.getDate() + 14);

  /* component states definations starts */
  const [policyOptions, setPolicyOptions] = useState([]);
  const [groupOptions, setGroupOptions] = useState([]);
  const remainingGroupUser = groupUsers;
  const [timezoneOptions, setTimezoneOptions] = useState([]);
  const openUserFormRef = useRef(null);
  const groupSelectData = useSelector(state => state.awarenessReducer.groupSelectReducer);
  /* component states definations ends */

  // The component instance will be extended
  // with whatever you return from the callback passed
  // as the second argument
  useImperativeHandle(ref, () => ({
    launchCampaign,
  }));

  /* Setting backend validation errors */
  useEffect(() => {
    for (const key in serverSideValErrors) {
      if (serverSideValErrors.hasOwnProperty(key)) {
        setError(key, {
          message: serverSideValErrors[key],
        });
      }
    }
  }, [serverSideValErrors]);

  /* Setting policies options */
  useEffect(() => {
    let data = policies.map((policy) => {
      return {
        value: policy.id,
        label: decodeHTMLEntity(policy.display_name),
      };
    });

    setPolicyOptions(data);
  }, [policies]);

  /* Setting groups options */
  useEffect(() => {
    updateGroupOptions(groupUsers);
    let onAudienceChangeCalled = false;
    groups.filter((group) => {
        if(group.name === groupSelectData.selectedGroup){
          onAudienceChangeCalled = true;
          onAudienceChange([{value: group.id, label: group.name}]);
        }
    })
    if(!onAudienceChangeCalled){
      const finale = groups.map(g => ({label: g.name, value: g.id}))
      onAudienceChange(finale)
    }
  }, [groups, groupUsers]);

  useEffect(() => {
    if(groups.length){
      const allSsoUsersOption = groups.find(s => s.name === groupSelectData.selectedGroup);
      if(allSsoUsersOption){
        return setValue('groups',[{
          label: allSsoUsersOption.name,
          value: allSsoUsersOption.id
        }]);
      }

      setValue('groups', groups.map(g => ({ label: g.name, value: g.id })));
    }
  }, [groups]);
  
  const updateGroupOptions = (groupUsers1) => {
    if (groupUsers1) {
      let data = groups.map((group) => {
        return {
          value: group.id,
          label: group.name,
        };
      }).concat(groupUsers1.map((groupUser) => {
        return {
          value: groupUser.group_id + '-' + groupUser.id,
          label: groupUser.first_name + ' ' + groupUser.last_name,
        };
      }));

      setGroupOptions(data);
    }
  }

  useEffect(() => {
    let data = TimezoneList.map((timezone) => {
      return {
        value: timezone.id,
        label: timezone.text,
      };
    });

    setTimezoneOptions(data);

  }, [TimezoneList]);

  /* Setting Default Timezone */
  useEffect(() => {
    reset({
      ...getValues(),
      timezone: globalSetting.timezone
    });
  }, []);

  const launchCampaign = async () => {
    try {
      let isValid = await trigger();

      /* Returing when invalid */
      if (!isValid) return false;

      /* submitting data */
      let formData = getValues();
      /* Adding data scope attribute */
      formData["data_scope"] = appDataScope;
      formData["campaign_type"] = "awareness";
      formData["control_id"] = controlId;
      formData["groups"] = formData['groups']?.map(g => g.value);

      const format = 'YYYY-MM-DD HH:mm:ss';

      const launchDate =  moment.utc(formData.launch_date).tz(globalSetting.timezone).format(format);
      formData.launch_date = moment.tz(launchDate, formData.timezone).utc().format(format);

      setIsFormSubmitting(true);

      Inertia.post(route("policy-management.campaigns.store"), formData, {
        onSuccess: (page) => {
          let {
            props: {
              flash: { data: campaign },
            },
          } = page;

          AlertBox(
            {
              title: "Awareness Campaign Scheduled!",
              text: "Awareness campaign has been scheduled for launch!",
              // showCancelButton: true,
              confirmButtonColor: "#b2dd4c",
              confirmButtonText: "OK",
              closeOnConfirm: false,
              icon:'success'
            },
            function (confirmed) {
              if (confirmed.value) {
                Inertia.visit(
                  route('compliance-project-control-show', [projectId, controlId, 'tasks'])
                );
              } else {
                setShowCampaignAddModal(false);
              }
            }
          );
        },
        onFinish: () => {
          setIsFormSubmitting(false);
        },
      });
    } catch (error) { }
  };

  const onAudienceChange = (val) => {
    // filtering only groups from selected value
    let groups_ = val.filter((selected) => Number.isInteger(selected.value));
    if (groups_.length) {
      // getting group ids
      let groupIds = groups_.map((group) => group.value);
      let newGroupUsers = [];
      // loop through groupIDs
      groupIds.forEach(groupId => {
        let selectedGroup = groups.filter(group => group.id == groupId);
        if (selectedGroup.length > 0 && selectedGroup[0].users.length > 0) {
          selectedGroup[0].users.forEach(user => {
            // pop object based on attribute value
            if (newGroupUsers.length > 0) {
              newGroupUsers = newGroupUsers.filter(groupUser => groupUser.email != user.email);
            }
            else {
              newGroupUsers = remainingGroupUser.filter(groupUser => groupUser.email != user.email);
            }
          });
        }
      });
      updateGroupOptions(newGroupUsers);
    } else {
      //if only users are selected
      updateGroupOptions(groupUsers);
    }
  }

  return (
    <Fragment>
      <div className="row">
        <div className="col-md-12">
          <div className="mb-3">
            <label htmlFor="name" className="form-label">
              Name <span className="required text-danger">*</span>
            </label>
            <input
              type="text"
              name="name"
              className="form-control"
              {...register("name")}
              id="name"
              placeholder=""
              value="Awareness Campaign"
            />
            <p className="invalid-feedback d-block">{errors.name?.message}</p>
          </div>
        </div>
        {policies.length > 0 && (
        <div className="col-md-12" style={{display:'none'}}>
          <div className="mb-3">
            <label htmlFor="policies" className="form-label">
              Policy(ies) <span className="required text-danger">*</span>
            </label>

            <Controller
              control={control}
              name="policies"
              options={policyOptions}
              defaultValue={
                policies.map((policy) => {
                      return policy.id
                  })
              }
              render={({ field: { onChange } }) => (
                <Select
                  className="react-select"
                  classNamePrefix="react-select"
                  inputRef={ref}
                  onChange={(val) => onChange(val.map((c) => c.value))}
                  
                  defaultValue={
                    policies.map((policy) => {
                          return { label: policy.display_name, value: policy.id }
                      })
                  }
                  options={policyOptions}
                  isMulti
                />
              )}
            />
            <p className="invalid-feedback d-block">{errors.policies?.message}</p>
          </div>
        </div>)}
      </div> 
      {/* end of row */}
      <div className="row">
        <div className="col-md-4">
          <div className="mb-3">
            <label htmlFor="launch-date_add-form" className="form-label">
              Launch Date <span className="required text-danger">*</span>
            </label>
            <Controller
              control={control}
              name="launch_date"
              defaultValue={new Date()}
              render={({ field }) => (
                <Datetime
                  {...field}
                  displayTimeZone={globalSetting.timezone}
                  dateFormat={'DD/MM/YYYY'}
                  isValidDate={(current) => {
                    return current.isAfter(subDays(new Date(), 1));
                  }}
                />
              )}
            />
            <p className="invalid-feedback d-block">{errors.launch_date?.message}</p>
          </div>
        </div>
        <div className="col-md-4">
          <div className="mb-3">
            <label htmlFor="due-date_add-form" className="form-label">
              Due Date <span className="required text-danger">*</span>
            </label>
            <Controller
              control={control}
              name="due_date"
              defaultValue={dueDate}
              render={({ field }) => (
                <Datetime
                  {...field}
                  dateFormat={'DD/MM/YYYY'}
                  displayTimeZone={globalSetting.timezone}
                  isValidDate={(current) => {
                    return current.isAfter(new Date());
                  }}
                />
              )}
            />
            <p className="invalid-feedback d-block">{errors.due_date?.message}</p>
          </div>
        </div>
        <div className="col-md-4">
          <div className="mb-3">
            <label htmlFor="timezone-add-form" className="form-label">
              Time Zone <span className="required text-danger">*</span>
            </label>
            <Controller
              control={control}
              name="timezone"
              render={({ field: { onChange } }) => (
                <Select
                  className="react-select"
                  classNamePrefix="react-select"
                  inputRef={ref}
                  onChange={(val) => onChange(val.value)}
                  options={timezoneOptions}
                  defaultValue={
                    //defaultTimezone   <- This is not working, if fixed, replicate on campaignduplicateform as well
                    {
                      value: TimezoneList.filter((timezone) => timezone.id == globalSetting.timezone)[0].id,
                      label: TimezoneList.filter((timezone) => timezone.id == globalSetting.timezone)[0].text
                    }
                  }
                />
              )}
            />
            <p className="invalid-feedback d-block">{errors.timezone?.message}</p>
          </div>
        </div>
      </div>
      {groups.length > 0 && (
      <div className="row">
        <div className="col-md-10">
          <div className="mb-3 no-margin">
            <label htmlFor="group" className="form-label">
            Audience <span className="required text-danger">*</span>
            </label>
            <Controller
              control={control}
              name="groups"
              render={({ field: { onChange, value } }) => (
                <Select
                  className="react-select"
                  classNamePrefix="react-select"
                  inputRef={ref}
                  onChange={(val) => {
                    onChange(val);
                    onAudienceChange(val);
                  }}
                  value={value}
                  options={groupOptions}
                  isMulti
                  placeholder="Select Users/Groups"
                  components={{ NoOptionsMessage }}
                />
              )}
            />
            <p className="invalid-feedback d-block">{errors.groups?.message}</p>
          </div>
        </div>

        <div className="col-md-2 add-user-col">
          <div className="mb-3 no-margin" style={{marginTop:'28px'}}>
          <UsersAndGroups 
              ref={openUserFormRef}
              ssoIsEnabled={ssoIsEnabled}
              projectId={projectId}
              controlId={controlId}
          >
          </UsersAndGroups>
          <LoadingButton
              className="btn btn-primary waves-effect waves-light float-end add-user"
              onClick={() => {
                  openUserFormRef.current.newGroupModel();
              }}
          >New Group</LoadingButton> 
          </div>
        </div>
      </div>)}
      {groups.length == 0 && (
      <div className="row">
        <div className="col-md-10">
          <div className="mb-3 no-margin">
            <label htmlFor="group" className="form-label">
            Audience <span className="required text-danger">*</span>
            </label>
            <Controller
              control={control}
              name="groups"
              render={() => (
                <Select
                  className="react-select"
                  classNamePrefix="react-select"
                  placeholder="Select Users/Groups"
                  components={{ NoOptionsMessage }}
                />
              )}
            />
            <p className="invalid-feedback d-block">{errors.groups?.message}</p>
          </div>
        </div>
        <div className="col-md-2 add-user-col">
          <div className="mb-3 no-margin" style={{marginTop:'28px'}}>
          <UsersAndGroups 
              ref={openUserFormRef}
              ssoIsEnabled={ssoIsEnabled}
              projectId={projectId}
              controlId={controlId}
          >
          </UsersAndGroups>
          <LoadingButton
              className="btn btn-primary waves-effect waves-light float-end add-user"
              onClick={() => {
                !overlayAdded && openUserFormRef.current.newGroupModel();
              }}
          >New Group</LoadingButton> 
          </div>
        </div>
      </div>)}
      <div className="row">
        <div className="col-md-12">
          <div className="mb-0 no-margin">
            <label htmlFor="group" className="form-label">
              Auto-enroll future group users{" "}
              <span className="required text-danger">*</span>
            </label>
            <Controller
              control={control}
              name="auto_enroll_users"
              defaultValue={'yes'}
              render={({ field: { onChange } }) => (
                <Select
                  className="react-select"
                  classNamePrefix="react-select"
                  inputRef={ref}
                  defaultValue={{ label: 'Yes', value: 'yes'}}
                  onChange={(val) => {
                    onChange(val.value);
                  }}
                  options={[
                    { label: 'Yes', value: 'yes'},
                    { label: 'No', value: 'no'}
                  ]}
                />
              )}
            />
          </div>
          <p className="invalid-feedback d-block">
            {errors.auto_enroll_users?.message}
          </p>
        </div>
      </div>
    </Fragment>
  );
}

export default forwardRef(AddAwarenessCampaignForm);
